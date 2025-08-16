# using_cURL examples and invoice watcher

## Quick start

- Configure credentials and archive GUID in `config.php`.
- Ensure PHP cURL is enabled and `curl.cainfo` points to a valid CA bundle (already set up during this session).

### Run examples

```powershell
php .\init.php
php .\Archive\listArchives.php
php .\Archive\listArchiveInformation.php
php .\Archive\archiveDocument.php
```

### Run invoice watcher service

Poll a folder (default: `FILE_STORAGE`, now pointing to `../JobArchive/`) and upload new files to the archive with index fields `Aktiv=1` and `DocuID=<generated>`.

```powershell
# Put your PDFs/images into the files directory first
php .\Service\watchInvoices.php
```

- State file is saved at `Service/.watch_state.json` to avoid double uploads.
- Adjust `WATCH_DIR` or the index field names in `Service/watchInvoices.php` to match your archive schema.

## Notes
- The watcher is a simple polling loop for CLI usage. For a Windows service, you can run it with Task Scheduler or NSSM.
- If your archive uses different index field names than `Aktiv`/`DocuID`, edit them in `watchInvoices.php`.
