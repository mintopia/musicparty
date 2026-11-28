# API changelog

## Deleting a Request that has already been sent

`DELETE /api/v1/parties/{party}/upcomingsongs/{upcomingsong}` now returns `409 Conflict` when the Request has already been sent to the music provider. It previously returned `400 Bad Request`. Clients that matched on 400 must handle 409.

## Request changed while it was being processed

`POST /api/v1/parties/{party}/upcomingsongs` can now return `409 Conflict` with the message "The request changed while it was being processed, please try again". It happens when the track was already a waiting Request at first look but was removed or sent to the provider before the Request lock was taken. Retrying the call is safe and resolves it; clients should handle 409 alongside the existing 400 and 422 responses.
