# An Import Saves Through the Panel, Not a Server Route

An import fills the form with the source language's eligible fields through the Panel's save, so they land as unsaved changes the editor can review or discard, behind Kirby's lock dialog and `update` permission check. We rejected a server route, such as one around `Translator::copyContent()`, in all three shapes:

- Writing `changes` from PHP leaves the same unsaved changes, minus the lock dialog.
- Writing `latest` publishes without review and has to refuse whenever unsaved changes exist.
- Deleting the current language's content file, so Kirby falls back to the default language, drops every translation the import leaves alone, ignores another user's lock, and rests on a fallback Kirby marks for removal (`Content/Version.php`, checked against 6.0.0-beta.3).
