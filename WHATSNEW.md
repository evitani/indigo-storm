# Indigo Storm Core
## Release 20.06

* You can now find objects using SearchQuery which allows more complex filters, limits, and ordering.
The current `db2->searchForObjects()` will be removed in a future release so existing code should be
updated to use SearchQuery instead
* You can now use `->pack()` on any model to create an array of the object to respond to GET requests
* You can now use `unpack($array)` to unpack the content of `$array` into an model. Useful for handling
`PUT` or `POST` requests
