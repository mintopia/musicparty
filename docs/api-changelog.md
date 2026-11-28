# API changelog

## Deleting a Request that has already been sent

`DELETE /api/v1/parties/{party}/upcomingsongs/{upcomingsong}` now returns `409 Conflict` when the Request has already been sent to the music provider. It previously returned `400 Bad Request`. Clients that matched on 400 must handle 409.
