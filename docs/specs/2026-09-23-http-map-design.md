# HTTP map design (v1.2.0)

## Goal

Extend the dependency graph beyond models and Filament resources with an HTTP map: routes, controllers, form requests, route-bound models, policies, events and listeners, and the jobs, mailables and notifications that the application dispatches. The map ships as a third scope, `http`, next to `filament` and `laravel`, in release 1.2.0.

A later, separate project (Blade and Livewire view tree) will build on the routes discovered here. It is out of scope for this document.

## Decisions

| Topic | Decision |
|---|---|
| Integration | New `GraphScope::Http`; graph, tree, table and inspector are reused. |
| Architecture | Extend `ApplicationSnapshot` with an `http` section discovered by `LaravelApplicationDiscoverer`. One discovery, one cache entry. |
| Granularity | One node per class. Controller methods live on edges and in the inspector. |
| Routes | Application routes only by default. Vendor routes are opt-in. |
| Middleware | Route attributes and a filter, never nodes. |
| Form request rules | Opt-in invocation of `rules()`, disabled by default. |
| Dispatch detection | Static token scan of the controller action, plus one level into injected application classes. |
| Safety | Never execute a controller action, never resolve a form request from the container, never instantiate a policy. |

## 1. Data model

### Node types

New `NodeType` cases, sorted after the existing ones:

| Case | Value | Label | Subtitle |
|---|---|---|---|
| `Route` | `route` | `GET /orders/{order}` (methods without `HEAD`, then URI) | Route name, if any |
| `Controller` | `controller` | Short class name | Namespace |
| `FormRequest` | `form_request` | Short class name | Namespace |
| `Policy` | `policy` | Short class name | Covered model short name |
| `Event` | `event` | Short class name | Namespace |
| `Listener` | `listener` | Short class name | `queued` when it implements `ShouldQueue` |
| `Job` | `job` | Short class name | `queued` when it implements `ShouldQueue` |
| `Mailable` | `mailable` | Short class name | `queued` when it implements `ShouldQueue` |
| `Notification` | `notification` | Short class name | `queued` when it implements `ShouldQueue` |

### Edge types

| Case | Value | Source → target | Label | Metadata |
|---|---|---|---|---|
| `RouteHandledByController` | `route_handled_by_controller` | route → controller | Controller method (`show`, `__invoke`) | `method` |
| `RouteRendersLivewire` | `route_renders_livewire` | route → livewire component | empty | none |
| `ControllerValidatesWith` | `controller_validates_with` | controller → form request | Comma-separated methods | `methods` |
| `ControllerUsesModel` | `controller_uses_model` | controller → model | Comma-separated methods | `methods`, `sources` (subset of `binding`, `type`, `static`) |
| `ModelGuardedByPolicy` | `model_guarded_by_policy` | model → policy | empty | `source` (`registered` or `convention`) |
| `EventHandledByListener` | `event_handled_by_listener` | event → listener | Listener method when not `handle` | `method` |
| `Dispatches` | `dispatches` | controller, listener or job → job, mailable, notification or event | Kind (`job`, `mail`, `notification`, `event`) | `kind`, `methods`, `via` (list of traversed classes, possibly empty), `confidence: static`, `locations` (list of `path:line`) |

Existing edge types are untouched. When one controller uses the same model through several methods, a single edge carries every method and every source.

### DTOs

All DTOs are `final readonly`, expose `fromArray()` and `toArray()`, carry a `DiscoveryStatus` and a `list<string> $warnings`, and follow ADR 0003.

- `HttpMapData`: `routes`, `controllers`, `formRequests`, `policies`, `events`, `listeners`, `dispatchables`. Every list defaults to empty.
- `RouteData`: `id`, `methods`, `uri`, `name`, `domain`, `actionType` (`controller`, `closure`, `livewire`, `view`, `redirect`), `controllerClass`, `controllerMethod`, `livewireClass`, `middleware` (declared names with groups expanded, in execution order), `boundParameters` (map of parameter name to model class), `file` (closures only).
- `ControllerData`: `id`, `class`, `file`, `actions` (map of method name to `ControllerActionData`).
- `ControllerActionData`: `formRequests` (classes), `models` (map of class to sources), `dispatches` (list of `DispatchReference`).
- `DispatchReference`: `class`, `kind`, `via` (list of classes), `location` (`path:line`).
- `FormRequestData`: `id`, `class`, `file`, `hasAuthorize`, `hasRules`, `rules` (`null` unless opt-in succeeded, otherwise map of field to list of normalized rules).
- `PolicyData`: `id`, `class`, `file`, `modelClass`, `abilities` (public methods declared on the policy, excluding `before` and `after`), `source`.
- `EventData`: `id`, `class`, `file`, `listenerClasses`, `closureListenerCount`.
- `ListenerData`: `id`, `class`, `file`, `method`, `events`, `queued`, `dispatches` (list of `DispatchReference`).
- `DispatchableData`: `id`, `kind` (`job`, `mailable`, `notification`), `class`, `file`, `queued`, `dispatches` (jobs only, empty otherwise).

`ApplicationSnapshot` gains `public HttpMapData $http` (constructor default: empty instance). `fromArray()` accepts a missing `http` key.

### Identifiers

`StableIdentifier` gains `route()`, `controller()`, `formRequest()`, `policy()`, `event()`, `listener()` and `dispatchable()`. `route()` hashes the route name when present, otherwise the sorted methods, the domain and the URI, so identifiers survive reordering of route files.

## 2. Discovery

### Safety rules

In line with ADR 0002:

- Controller actions are never called. No request is dispatched through the kernel.
- Form requests are never resolved from the container, because resolution triggers `ValidatesWhenResolved`.
- Policies are never instantiated.
- Everything else comes from reflection, framework registries or token scanning. Every step is wrapped so that a failure yields a warning and a `Partial` status, never an exception.

### Shared support: `Discovery/Support/SourceScanner`

Extracted from `LivewireComponentDiscoverer`: tokenization, import resolution before the class declaration, `Foo::` references, and next-significant-token helpers. `LivewireComponentDiscoverer` is refactored to use it with no behaviour change; its existing tests must stay green without edits.

New capabilities, all bounded to the line range of one method:

- Static class references (`Foo::`), resolved to fully qualified names.
- Dispatch patterns:

| Pattern | Detected class |
|---|---|
| `Foo::dispatch(`, `dispatchSync(`, `dispatchIf(`, `dispatchUnless(`, `dispatchAfterResponse(` | `Foo` |
| `dispatch(new Foo`, `dispatch_sync(new Foo` | `Foo` |
| `Bus::dispatch(new Foo`, `Bus::chain([new Foo, ...])`, `Bus::batch([new Foo, ...])` | every `Foo` |
| `Queue::push(new Foo` | `Foo` |
| `Mail::...->send(new Foo` or `->queue(new Foo`, `Mail::send(new Foo` | `Foo` |
| `->notify(new Foo`, `->notifyNow(new Foo`, `Notification::send(..., new Foo` | `Foo` |
| `event(new Foo`, `Event::dispatch(new Foo` | `Foo` |

- Collaborator calls: `$param->method(`, `$this->property->method(` and `$param(` (mapped to `__invoke`), returned with the variable or property name so the caller can map them to typed parameters.

The final kind is derived from the resolved class, not from the pattern: subclass of `Illuminate\Mail\Mailable` → mailable; subclass of `Illuminate\Notifications\Notification` → notification; key of the event dispatcher's listener map or reached through `event()` / `Event::dispatch()` → event; otherwise job. Classes that do not exist are dropped with a warning.

### Discoverers

Each discoverer implements `CollectsDiscoveryWarnings` and has a contract in `Contracts/`, like the existing ones.

1. **`RouteDiscoverer`** reads `Route::getRoutes()`, which also works with `route:cache`. A route is kept when:
   - its controller class matches `http.controller_namespaces`, or
   - its action is a closure whose file is outside `vendor/`, or
   - its action is a Livewire component matching the configured Livewire namespaces, or
   - its action is a `view` or `redirect` route defined in the application,

   and it does not match `http.exclude.names` or `http.exclude.uris` (`Str::is` patterns). With `http.include_vendor_routes`, the namespace and vendor-file checks are skipped. Middleware comes from `$route->gatherMiddleware()`, with groups expanded through `Router::getMiddlewareGroups()`; aliases such as `auth` stay as written so the filter and the inspector show familiar names. Bound parameters come from `signatureParameters(UrlRoutable::class)`.

2. **`ControllerDiscoverer`** starts from the controllers referenced by kept routes. There is no directory scan. For each routed action:
   - parameters typed with a `FormRequest` subclass → form requests;
   - parameters typed with a `Model` subclass → models with source `type`, or `binding` when the parameter name matches a route parameter;
   - static model references in the method body → models with source `static`;
   - dispatch patterns in the method body → dispatches;
   - when `http.follow_injected_classes` is enabled, collaborator calls on parameters or promoted constructor properties typed with a class under `http.application_namespaces` (excluding models, form requests and controllers) are followed once: the called methods are scanned for dispatches and static model references, and results carry the collaborator in `via`.

3. **`PolicyDiscoverer`** reads `Gate::policies()` (source `registered`), then reproduces Laravel's naming convention with `class_exists` for every discovered model without a registered policy (source `convention`). A custom `Gate::guessPolicyNamesUsing()` callback is not observed; this is documented.

4. **`EventDiscoverer`** reads `Event::getRawListeners()`, which includes Laravel event discovery. Wildcard events are ignored. A listener is kept when its class matches `http.application_namespaces`, even when the event is a framework event such as `Illuminate\Auth\Events\Login`. An event is kept when it matches `http.application_namespaces` or has at least one kept listener. Closure listeners are counted, not turned into nodes. Kept listeners' handling methods are scanned for dispatches using the same one-level rule.

5. **Dispatchables**: every job, mailable and notification referenced by a dispatch becomes a `DispatchableData`. Job `handle()` methods are scanned for dispatches with the one-level rule. Mailables and notifications are not scanned.

6. **Form request rules** (only with `http.form_request_rules`): `new $class()` without the container, `setContainer(app())`, then `rules()` inside `try`/`catch (Throwable)`. Rules are normalized: string rules are split on `|`, objects become their class name, closures become `closure`. A failure keeps `rules` at `null`, sets `Partial` and adds a warning naming the exception.

### Orchestration

`LaravelApplicationDiscoverer` runs the HTTP discoverers after models and Livewire components, only when `http.enabled` is true. Models referenced by controllers, policies or route bindings that are not yet in the snapshot are added, the same way `addResourceModels()` and `addLivewireComponentModels()` already work. Their relations are discovered like any other model.

### Configuration

```php
'http' => [
    'enabled' => true,
    'controller_namespaces' => ['App\\Http\\Controllers\\'],
    'application_namespaces' => ['App\\'],
    'include_vendor_routes' => false,
    'exclude' => [
        'names' => [],
        'uris' => [],
    ],
    'follow_injected_classes' => true,
    'form_request_rules' => false,
],
```

`DiscoveryContext` gains the matching properties. They are part of `toArray()`, hence of the cache fingerprint.

## 3. Scope and interface

### Scope rules

`GraphScope::Http` keeps:

- every route node;
- every node reachable from a route through outgoing HTTP edges (`route_handled_by_controller`, `route_renders_livewire`, `controller_validates_with`, `controller_uses_model`, `dispatches`, `event_handled_by_listener`);
- the policies of the kept models;
- every event and listener node, even without a detected dispatcher (the existing orphan badge then applies);
- every edge whose two endpoints are kept, so relations between kept models remain visible.

Existing scopes keep their current output:

- `restrictToFilamentScope()` excludes the new node types explicitly, otherwise `model_guarded_by_policy` would pull policies in.
- The Laravel scope, which does not filter today, excludes the new node types.

`http.enabled` and `DependencyGraphPlugin::allowHttpScope(bool)` control whether the scope is offered, mirroring `allowLaravelScope()`. `DefaultDependencyGraphManager`, `DiscoverApplication` and the page fall back to the Filament scope when the HTTP scope is disabled.

### Graph

One shape per new node type, colours resolved from the Filament palette like the existing types, and a legend entry for each. The hierarchical layout reads left to right: route, controller, form request and model, policy, with dispatches as branches.

### Tree

In the HTTP scope, roots are routes grouped by their first URI segment (`/orders`, `/account`, `/` for the root). Each route unfolds into controller and method, then form requests, models and dispatches, then listeners and their own dispatches. The existing `treeNode()` cycle protection is reused.

### Table

Three datasets, only offered in the HTTP scope:

- **Routes**: methods, URI, name, action, middleware, form requests, models.
- **Events**: event, listeners, dispatched by, queued listeners.
- **Dispatches**: kind, class, queued, dispatched by (with `via`).

`TABLE_DATASETS` is extended and the dataset falls back to `models` when the scope changes.

### Inspector

One inspector per new node type, registered like the existing ones:

- Route: methods, URI, name, domain, action, middleware in order, bound parameters.
- Controller: one row per action with its routes, form requests, models and dispatches, including `via` and `path:line`.
- Form request: `authorize()` and `rules()` presence, the normalized rules when available, otherwise a hint naming `http.form_request_rules`.
- Policy: model, abilities, source.
- Event: listeners, closure listener count, dispatchers.
- Listener: events, method, queued, dispatches.
- Job, mailable, notification: kind, queued, dispatched by, dispatches (jobs).

The existing model inspector gains an "HTTP" section listing the routes that bind the model, the controllers that use it and its policy.

### Filters

- New node types appear automatically in the node type filter.
- A middleware filter, shown only in the HTTP scope, keeps routes that have or do not have the selected middleware. It is persisted in the URL like the other filters.
- Search matches route URIs and names.

### Translations and assets

English and French strings for node types, edge types, datasets, filters and inspector sections. Node styles live in `resources/js/dependency-graph.js`; `dist/` is rebuilt with `npm run build`.

### Volume

No automatic grouping in 1.2.0. On large route sets, the tree, the table and focus mode are the recommended entry points.

## 4. Cache, exports, tests, release

### Cache

- `SchemaVersion::CURRENT` becomes `1.3`, so cached snapshots in the previous format are rebuilt (ADR 0004).
- The fingerprint includes the `http` section.
- `dependency-graph:cache` and `dependency-graph:clear` need no change.

### Exports

- JSON: no change; new nodes and edges use the existing schema.
- Mermaid: edge labels for `route_handled_by_controller` (method) and `dispatches` (kind), in addition to model relations.
- `dependency-graph:export --scope=http` works through the existing scope option.

### Tests

Written test first, with Pest.

- Fixtures under `tests/Fixtures/Http/`: a resource controller, an invokable controller, a controller using an injected action class, a form request with plain rules, a form request whose `rules()` calls `$this->route()`, a convention policy, a registered policy, an event with a synchronous and a queued listener, a job, a mailable, a notification. Routes registered in `TestCase`: controller, closure, full-page Livewire component, and one vendor-like route that must be excluded.
- Unit: `SourceScanner` (every pattern, plus comments and string literals ignored), rule normalization, new stable identifiers, new DTO round-trips.
- Feature: one test per discoverer, extended `ApplicationDiscoveryTest`, scope filtering for all three scopes including non-regression for Filament and Laravel, page tests for the scope selector, new datasets, middleware filter and inspectors, Mermaid labels.
- Livewire discovery tests pass unchanged after the `SourceScanner` extraction.
- Architecture tests cover the new classes.
- Pint, PHPStan and the full suite pass before every commit.

### Documentation

- ADR 0010: HTTP map discovery, exact versus heuristic sources, safety rules, the one-level rule.
- README: HTTP scope in Features and Scopes, `http` configuration, a "Detection limits" section (explicit `Route::bind()` bindings, dynamically built class names, dispatches deeper than one level, custom policy name guessers).
- CHANGELOG: `[1.2.0]` with an Added list and the `SchemaVersion` bump.

### Git and release

- Work happens on `feature/http-map`, branched from `main`.
- Atomic commits per plan step, authored by Alexandre Ribes, with no tool attribution.
- A pull request to `main`; the `v1.2.0` tag and GitHub release follow the existing format, only on request.
