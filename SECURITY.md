# Security policy

## Supported versions

Security fixes are applied to the latest minor release line.

## What this package exposes

Filament Dependency Graph displays application architecture metadata: class names, namespaces, table names, relationship keys and panel structure. It never displays record data, environment variables or credentials, and it never executes write operations. Architecture metadata is still sensitive: treat the page as confidential.

By default the page is only visible when the application runs in the local environment. If you override the visibility callback, restrict access to trusted, authenticated users.

## Reporting a vulnerability

Please do not open public issues for security reports. Email alexandre@laboiteacode.fr with:

- a description of the vulnerability and its impact;
- reproduction steps or a proof of concept;
- affected versions.

You will receive an acknowledgement within a few days. Please allow a reasonable window for a fix and coordinated disclosure before publishing details.
