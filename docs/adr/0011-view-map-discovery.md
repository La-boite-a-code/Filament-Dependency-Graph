# ADR 0011: View map discovery from template text and framework resolvers

## Status

Accepted. Complements ADR 0010 (HTTP map) with the Blade layer.

## Context

After the HTTP map, the missing piece was the rendering side: which layout a page extends, which partials, components and Livewire components it includes, and, the other way round, where a partial or a component is used before changing it. Blade gives two ways to learn this. The compiled templates are exact, but compiling runs the directives and precompilers the application registers, which is application code. The raw templates are plain text, but names in them only mean something once resolved: `<x-alert>` may be a class, an anonymous component or a package component, `@include('filament::…')` a package view.

## Decision

The view map is a fourth scope, `views`, built from a `views` section of the application snapshot.

1. **Templates are read as text, never compiled.** A scanner lists the Blade files of the view paths (published `vendor/` overrides excluded by default), blanks out comments, `@verbatim`, `@php` and PHP blocks while keeping line breaks, then extracts `@extends`, the `@include` family, `@each`, `@component`, `@livewire`, `<x-…>` and `<livewire:…>` with their line. A name that is not a string literal becomes a dynamic reference.
2. **Names are resolved by the framework.** View names go through the view finder, component tags through `ComponentTagCompiler::componentClass()` fed with the registered aliases and namespaces, Livewire names through Livewire's own registry: the Livewire 4 finder (classes, single-file and multi-file components), or the Livewire 3 aliases read through reflection plus the class namespace convention. These resolvers only check files and class existence. Livewire's missing-component resolvers are never called, because they may compile components.
3. **Owners are read, not run.** Livewire classes (`render()` literals, `->layout()`, `#[Layout]`), Blade class components (`render()`), Filament classes (default value of a `$view` property declared by the application class, or literals of `getView()`), mailables and notifications (`content()`, `build()`, `toMail()` named arguments and calls), routes (`Route::view`) and controller actions (`view()`, `View::make()`, `->view()`) are read through reflection and the shared token scanner. Nothing is instantiated.
4. **What is not explored stays visible.** Package views and components are leaf nodes; unresolvable names are "missing" leaves; runtime names are dynamic nodes. Exploring package views is opt-in.
5. **Kinds are inferred from usage**: Livewire component, layout (extended, used as a layout, or under a `layouts` directory), component (under `components/`), mail, page (rendered by a route, a controller, a Filament page), otherwise partial.
6. **The HTTP scope follows `renders_view` one hop**, action-aware for controllers, so a route shows the page it renders without unfolding the view tree.

## Consequences

- The Filament and Laravel scopes are unchanged; view node types are excluded from them.
- `SchemaVersion` becomes `1.4`; snapshots without `views` still deserialize.
- Template contents are never kept in memory, only their references; every template is read once per discovery.
- Known limits, documented for users: names computed at runtime, view composers, `@yield`/`@section`/`@push`/`@stack` and slots are not mapped; a `render()` that does not return a literal view leaves its owner without a view; package views appear as leaves unless explored.
