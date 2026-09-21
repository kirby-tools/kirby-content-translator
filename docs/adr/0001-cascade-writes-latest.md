# A Cascade Is Written to `latest`, Never to a `changes` Version

A run can translate a cascade along with its host: the models the host's `cascade` option names, such as Kirby Modules pages or the host's files. The obvious design leaves those translations as unsaved changes, so the editor reviews and saves them together with the host. We write them straight to the `latest` version instead, through the path a batch translation already uses.

Kirby 5 rules the `changes` design out (checked against 5.6.0). With content locking on, Kirby's default, every write to a `changes` version stamps the editing user as its lock (`Content/Version.php`), so a cascade of six modules would leave six locks behind. And `Version::publish()` publishes exactly one language, which the Panel's save passes as `'current'` (`Api/Controller/Changes.php`) – the translations of a batch would stay unpublished in every language but the one open in the Panel.

## Consequences

- A single-language translation is asymmetric: the host's fields wait in the form, its cascade is already saved, and discarding the host does not take the cascade back. Title and slug have always been saved directly too. The dialog that starts a run therefore counts the cascade and says it is saved directly – a single-language translation opens it for that sentence alone when there is no strategy to pick.
- Writing `latest` bypasses Kirby's lock check, because `latest` content never carries a lock. `BatchTranslation::writeLanguage()` therefore checks the lock and unsaved changes for every model. A model that fails the check is skipped and reported by name, and the run continues.
