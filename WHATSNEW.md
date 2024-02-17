# Indigo Storm Core
## Release 20.05
Releases 20.02 through 20.04 were missed due to contributor circumstances. This release incorporates bugfixes and new 
features to get Indigo Storm up to date with progress.

* *Bugfix* Corrected an error that meant minor releases less than .10 were incorrectly reporting being out of date
* Errors are now sent with a CORS header that allows any origin
* All inbound headers are now allowed on CORS requests
* Added new `-env=gae73` and `--php7` options to `tools/developer release`
* Whenever objects are saved, revisions of their old content are now retained. Revisions can be suppressed or set to 
instead store only an audit line (either per object type or individual save)
* **BREAKING CHANGE** Keys removed from DataTables are no longer retained on the object, so persisting an object will
remove keys that aren't included in the in-memory copy. This is a change to how they were previously handled, where keys
would be retained in their latest version should they be removed in-memory
* You can delete objects using the `->delete()` method. By default, deleted objects will be backed up indefinitely, but
you can set them to not back up, or for their backups to expire after 7 or 30 days (expiring backups won't be deleted
without garbage collection, coming soon)
* Calls to services or routes that don't exist, or calls to routes using unexpected methods or arguments, will now 
result in verbose errors.
