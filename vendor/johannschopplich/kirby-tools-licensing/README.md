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

On success, the dialog shows a toast, emits `kirby-tools.license.activate` and `kirby-tools.license.activate.{pluginId}` with `package` and `status` in the payload, and reloads the current view. On failure, the dialog shows a translated error message.

## Classes

### `Licenses`

Reading a license refreshes it when the plugin version has changed, and `getStatus()` returns `active`, `inactive`, `invalid`, `incompatible`, or `upgradeable`.

Activation sends the email and the license key, of the form `KT{generation}-{hash}-{hash}`, to the Kirby Tools API at `https://repo.kirby.tools/api` and stores the license in the `.kirby-tools-licenses` file.

### `PluginLicense`

`PluginLicense` extends Kirby's [`License`](https://getkirby.com/docs/reference/objects/plugin/license) class and maps the status to [Kirby's license status system](https://getkirby.com/releases/5/plugin-license) for the Panel.

## Used By

- [kirby-copilot](https://kirby.tools/copilot)
- [kirby-content-translator](https://kirby.tools/content-translator)
- [kirby-live-preview](https://kirby.tools/live-preview)
- [kirby-seo-audit](https://kirby.tools/seo-audit)

## License

[AGPL](./LICENSE) License © 2024-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
