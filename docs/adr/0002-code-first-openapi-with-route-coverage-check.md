# Code-first OpenAPI with a route-coverage check

There is no first-party Laravel package for OpenAPI. We preferred spec-first but accepted code-first generation from Form Requests and API Resources, on the condition that CI verifies no API route is undocumented. The generated spec is committed, and a test fails when the spec drifts from the code or when any `/api` route is missing from it.

The Vue UI is served through Inertia props, not the public API. Inertia controllers and API controllers both call the same application Actions, so the API offers every capability the UI has without the UI depending on it.
