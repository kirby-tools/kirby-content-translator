# Kirby Tools Licensing Backend

Backend licensing system for all commercial [Kirby Tools plugins](https://kirby.tools). This package handles license activation, status, and storage on the server side, which enables:

- License status and enforcement
- Seamless license activation from within Kirby
- Checking the installed plugin version against the license's compatibility constraint
- [Panel integration for license status display](https://getkirby.com/releases/5/plugin-license)

For Panel-side licensing UI components, see [@kirby-tools/licensing](https://github.com/kirby-tools/licensing-panel).

## Classes

The licensing classes handle:

- **License Activation**: Confirms email and license key with the license server
- **Plugin Integration**: Seamlessly integrates with Kirby 5's native plugin license system
- **Compatibility Constraint**: Checks the installed plugin version against the constraint the license carries, using semantic versioning
- **License Management**: Handles license activation, storage, and status reporting
- **Automatic Updates**: Refreshes license data when plugin versions change

### `Licenses`

The main licensing engine that:

- Reads and writes license data to `.kirby-tools-licenses` file
- Communicates with the Kirby Tools API at `https://repo.kirby.tools/api`
- Checks license keys against the pattern `KT{generation}-{hash}-{hash}`
- Manages license status: `active`, `inactive`, `invalid`, `incompatible`, `upgradeable`
- Handles license activation via email and license key

### `PluginLicense`

Kirby 5 integration adapter that:

- Extends Kirby's native [`License`](https://getkirby.com/docs/reference/objects/plugin/license) class
- Maps internal license statuses to [Kirby's license status system](https://getkirby.com/releases/5/plugin-license)
- Provides standardized license display in the Kirby Panel
- Integrates with Kirby's plugin management interface

## License

[AGPL](./LICENSE) License © 2024-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
