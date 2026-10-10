# Code-first OpenAPI with a route-coverage check

> **Superseded** by ADR-0015. The code-first approach and the route-coverage check stay, but the in-house generator built under this ADR is replaced by `dedoc/scramble`, and coverage now extends to every API Resource, channel and broadcast event.

There is no first-party Laravel package for OpenAPI. We preferred spec-first but accepted code-first generation from Form Requests and API Resources, on the condition that CI verifies no API route is undocumented. The generated spec is committed, and a test fails when the spec drifts from the code or when any `/api` route is missing from it.

The Vue UI is served through Inertia props, not the public API. Inertia controllers and API controllers both call the same application Actions, so the API offers every capability the UI has without the UI depending on it.
