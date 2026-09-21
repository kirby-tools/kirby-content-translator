# Playground

Two modes, switched by `KIRBY_DEBUG` in `.env`:

- **Shared demo** (`false`): no frontend, every URL goes to the Panel, `/panel/login` signs in the `playground` role, changes are never written.
- **Local testing** (`true`): pages render under `/` and the language roots, `/panel/login` signs in as admin, `/panel/login?role=playground` as the role that may preview but not update.

`composer dev` serves it on `localhost:8000`. The admin account `admin@example.com` has no password; only the login route signs it in.

## Where to Test What

| Page | Control | Covers |
| --- | --- | --- |
| Company | view button | `cascade` over Kirby Modules pages and the page's files, a structure field |
| Projects → Creatious Labs | section | `cascade` over the case studies and the project's files, German only – a batch translation fills `es`, `fr` and `zh` |
| Blog → Exploring the Universe | view button | blocks and KirbyTags |
| Site | view button | `importFrom: all`, `title: true`, no batch translation |

`TRANSLATOR_STRATEGY` in `.env` swaps the default strategy for a stub, so a notification can be tested without a DeepL request: `blank` rejects every unit, `partial` rejects the units that hold a KirbyTag, and `failing` fails the language named by `TRANSLATOR_FAILING_LANGUAGE` (`fr` by default). An AI translation goes from the Panel to Kirby Copilot and ignores the stub.

Content locking is off, so two tabs never block each other. `KIRBY_CONTENT_LOCKING=true` turns it on for testing how a run reports a model another user edits.

`pnpm run playground:reset` restores the content.
