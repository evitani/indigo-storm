# Indigo Storm Core
## Release 21.17

The next step in Indigo Storm capability, this includes a number of additional features as well as stability 
and usability improvements.

### What's New?
* You can now define payloads, schemas that complete stricter checks on request and response bodies
  * Define required and optional fields you expect in the payload
  * Set data types for fields, with validation being handled prior to your controller
* *BETA* XML is now supported as a response type alongside JSON and binary files when using payload-based responses.
  Use a payload to define the required schema. It is not supported as a request body type.
* The `$request->getUploadedFiles()` function has been re-implemented, along with a new `$request->getUploadedFile()`
  option, and returns a `Core\Payloads\File` for each uploaded file.
* The `Core\Payloads\File` provides all interface options needed for handling files, removing the need to manually
convert between base64 and binary, as well as providing a size API.
* Files returned by endpoints now include their file name as a `Content-Disposition` header to browsers.
* Deletion of keys from DataTables is now supported with the `->del` function.
* A new `->loadOrCreate()` function now exists on objects, which avoids the need to catch exceptions when instantiating
  an object that may already exist.
* Tasks set to run in Google Cloud can now be delayed, rather than occurring instantly
* Services can list `definitions` in their `service.yaml` file which will create constants at runtime. Use 
  `storm define ServiceName/ConstantName value` to create these and `storm undefine ServiceName/ConstantName` to remove
  them.
* PHP 7.4 is now the default version for releasing to App Engine.
* Version calculation logic has been removed from the primary app workflow
* Various bugs have been resolved, including:
    * The router no longer triggers PHP warnings during OPTION requests
    * SearchQuery now handles chained filters correctly
    * DataTable now handles empty queries without triggering PHP warnings
    * CORs requests are handled more gracefully and with more information
    * Root properties of ConfigItems are now accessible

### Breaking Changes
* Service-specific constants have been removed from `app/definitions.inc.php`. Ensure all required constants are defined
in your `service.yaml` going forward (see above).
* To return files as responses, you must now use the `Core\File\Payload` object instead of an array.
