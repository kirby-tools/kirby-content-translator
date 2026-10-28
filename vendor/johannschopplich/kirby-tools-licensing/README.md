# Kirby Tools Licensing Backend

Backend licensing system for all commercial [Kirby Tools plugins](https://kirby.tools). This package handles license activation, status, and storage on the server side, which enables:

- License status and enforcement
- Seamless license activation from within Kirby
- Checking the installed plugin version against the license's compatibility constraint
- [Panel integration for license status display](https://getkirby.com/releases/5/plugin-license)
- One activation dialog for the System view and every plugin button

For Panel-side licensing UI components, see [@kirby-tools/licensing](https://github.com/kirby-tools/licensing-panel).

Version 1 requires Kirby 6 and PHP 8.3. Kirby 5 plugins stay on the `^0.9` line, whose classes live under `JohannSchopplich\Licensing\`, so both lines can sit side by side in one site and read the same license file.

## Usage

```php
use Kirby\Cms\App as Kirby;
use KirbyTools\Licensing\LicensePanel;
use KirbyTools\Licensing\PluginLicense;

$packageName = 'johannschopplich/kirby-copilot';

Kirby::plugin(
    name: 'johannschopplich/copilot',
    extends: [
        'areas' => [
            'system' => fn () => [
                'dialogs' => LicensePanel::dialogs($packageName, 'Kirby Copilot')
            ]
        ],
        'translations' => LicensePanel::translations()
    ],
    license: fn ($plugin) => new PluginLicense($plugin, $packageName)
);
```

`LicensePanel::dialogs()` registers `{vendor}-{package}/activate` and `{vendor}-{package}/license`. A field hands the activation dialog's id and the license status to its Vue component, whose buttons open the dialog by that id:

```php
use Kirby\Form\Field\DisplayField;
use Kirby\Reflection\Attributes\Prop;
use KirbyTools\Licensing\Licenses;
use KirbyTools\Licensing\LicenseUtils;

final class CopilotField extends DisplayField
{
    private const PACKAGE_NAME = 'johannschopplich/kirby-copilot';

    #[Prop(computed: true)]
    protected string $licenseDialog = '';

    #[Prop(computed: true)]
    protected string $licenseStatus = 'inactive';

    public function licenseDialog(): string
    {
        return LicenseUtils::toActivationDialog(self::PACKAGE_NAME);
    }

    public function licenseStatus(): string
    {
        return Licenses::read(self::PACKAGE_NAME)->getStatus();
    }
}
```

On success, the dialog shows a toast, emits `kirby-tools.license.activate` and `kirby-tools.license.activate.{pluginId}` with `package` and `status` in the payload, and reloads the current view. A failure reaches the Panel translated from its `ActivationOutcome`.

## Classes

The licensing classes handle:

- **License Activation**: Confirms email and license key with the license server
- **Plugin Integration**: Seamlessly integrates with Kirby's native plugin license system
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

Kirby integration adapter that:

- Extends Kirby's native [`License`](https://getkirby.com/docs/reference/objects/plugin/license) class
- Maps internal license statuses to [Kirby's license status system](https://getkirby.com/releases/5/plugin-license)
- Provides standardized license display in the Kirby Panel
- Integrates with Kirby's plugin management interface

## License

[AGPL](./LICENSE) License © 2024-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
