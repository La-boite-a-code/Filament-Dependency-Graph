# ADR 0010: HTTP map discovery without executing application code

## Status

Accepted. Extends ADR 0002 to routes, controllers, form requests, policies, events and dispatched classes.

## Context

The graph described data (models, relations) and the Filament layer (panels, resources), but not the request flow: which route reaches which controller, what it validates with, which models it touches, what it dispatches, and who listens. A useful HTTP map has to be exact where the framework keeps a registry and honest where only the source code can tell.

Two risks drive the design. Running application code to learn about it can trigger side effects: resolving a form request from the container validates it, instantiating a controller runs its constructor dependencies, calling a policy may query the database. And statically reading code is inherently partial: dynamic class names, container indirection and deep call chains cannot be followed safely.

## Decision

The HTTP map is a third scope, `http`, built from a new `http` section of the application snapshot. Every fact comes from one of three sources, from the most to the least exact:

1. **Framework registries.** Routes from the router (`Route::getRoutes()`, which also works with `route:cache`), listeners from the event dispatcher (`getRawListeners()`, which includes event discovery), policies from the gate (`Gate::policies()`, the `#[UsePolicy]` attribute, then the gate's own `guessPolicyName()` read through reflection, so a custom `guessPolicyNamesUsing()` callback is honoured).
2. **Reflection.** Controller actions come from the routes that point to them, never from a directory scan. Typed action parameters give form requests and models; a model parameter matching a route parameter (or its snake case form, as implicit binding does) is a route model binding. Route middleware combines the route definition with the framework's controller attributes (`#[Middleware]`, `#[Authorize]`, `#[WithoutMiddleware]`), read from reflection; groups are expanded and aliases paired with their classes. A controller's static `middleware()` method is not called.
3. **Static reading of the source.** A shared token scanner (`SourceScanner`, extracted from the Livewire discoverer) reads a method body: `X::dispatch*()` / `X::broadcast()` static calls, `new X` expressions, static model references such as `Order::query()`, and calls on typed collaborators. A class only becomes a dispatch when its type says what it is: mailable, notification, event (listener-map key, events `Dispatchable`, broadcast or after-commit contract), or job (bus `Dispatchable` or `ShouldQueue`). Every dispatch edge carries `confidence: static` and its `path:line` locations.

Static reading follows **one level** of injected application classes: when an action calls `$action->execute()` or `$this->orders->archive()` on a typed parameter or promoted property whose class lives in `http.application_namespaces`, the called method is read too and its findings are attributed to the controller with a `via` list. The same rule applies to listener methods and job `handle()` methods; jobs dispatching jobs are followed to a fixed point.

Two narrow exceptions to "never execute" remain, both deliberate. A custom `Gate::guessPolicyNamesUsing()` callback is called, because it is a pure name mapping that Laravel itself calls to resolve policies, and ignoring it would report wrong policies. Form request rules are read only on request: with `http.form_request_rules` enabled, the form request is created with `new` (never through the container), then `rules()` is called inside a `try`/`catch`. A failure, typically a rule depending on `$this->route()` or the authenticated user, keeps the request partial with a warning.

One node represents one class; controller methods live on edges and in the inspector. The HTTP scope follows edges from the routes (and from events and listeners, so an event without detected dispatcher still appears with a `No dispatcher found` badge). Because a controller node stands for all its actions, the traversal only follows the controller edges of actions reached by a kept route, which keeps the middleware filter precise.

## Consequences

- The Filament and Laravel scopes are unchanged: HTTP node types never enter them.
- Adding the HTTP section bumped `SchemaVersion` to `1.3`; snapshots without the section still deserialize.
- Known blind spots, documented for users: explicit `Route::bind()` bindings, dynamically built class names (`new $class`, `app($name)`), dispatches deeper than one collaborator level, middleware returned by `HasMiddleware::middleware()`, and closures restored from the route cache, which are skipped with a warning because their origin cannot be checked.
- Tokens weigh about twenty times their source, so the scanner keeps only the sixteen most recent files (enough for one controller and its collaborators), reads Livewire components without caching, and is flushed after every run so long-running processes never read stale sources.
- Every class is loaded inside a `try`: a listener or policy that cannot be autoloaded degrades to a warning instead of dropping the whole map.
