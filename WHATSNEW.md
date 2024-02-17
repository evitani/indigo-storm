# Indigo Storm Core
## Release 20.07

* Minor bug fixes to reduce unnecessary logging and revision saving
* Endpoints can now be restricted to authenticated users (or subsets of) using the `access-control`
key in their service definition. _NOTE: This requires migration to the latest User service, which
includes breaking changes to SAML handling. Read the notes before updating._
