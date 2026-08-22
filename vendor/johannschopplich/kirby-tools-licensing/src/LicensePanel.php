<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Closure;
use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\I18n;
use Throwable;

/**
 * Every Kirby Tools plugin registers these extensions verbatim, so the dialog IDs
 * built here must stay in sync with the ones `PluginLicense::toKirbyStatus` emits.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
final class LicensePanel
{
    /**
     * Translation keys for license activation failures, keyed by exception message.
     *
     * The keys are matched verbatim: they come either from `LicenseActivator`
     * or, like `Unauthorized`, from the licensing API's error response. An
     * unmapped message is surfaced to the user untranslated.
     */
    public const ACTIVATION_ERROR_KEYS = [
        'Unauthorized' => 'kirby-tools.license.error.invalidCredentials',
        'License key already activated' => 'kirby-tools.license.error.alreadyActivated',
        'License key not valid for this plugin' => 'kirby-tools.license.error.invalid',
        'License key not valid for this plugin version' => 'kirby-tools.license.error.incompatible',
        'License key not valid for this plugin version, please upgrade your license' => 'kirby-tools.license.error.upgradeable'
    ];

    private static App|null $checkedApp = null;

    private static array $checkedLocales = [];

    public static function api(string $packageName): array
    {
        $apiPrefix = LicenseUtils::toApiPrefix($packageName);

        return [
            [
                'pattern' => "{$apiPrefix}/activate",
                'method' => 'POST',
                'action' => self::repairingTranslationCache(function () use ($packageName) {
                    try {
                        $licenses = Licenses::read($packageName);
                        return $licenses->activateFromRequest();
                    } catch (Throwable $e) {
                        throw LicensePanel::activationFailure($e, $packageName);
                    }
                })
            ]
        ];
    }

    public static function dialogs(string $packageName, string $pluginLabel): array
    {
        $dialogPrefix = LicenseUtils::toPackageSlug($packageName);
        $pluginId = LicenseUtils::toPluginId($packageName);

        $dialogs = [
            // Reached from `PluginLicense::toKirbyStatus` for active, upgradeable and incompatible licenses.
            "{$dialogPrefix}/license" => [
                'load' => function () use ($packageName, $pluginId, $pluginLabel) {
                    $licenses = Licenses::read($packageName);
                    $license = $licenses->getLicense();
                    $status = $licenses->getStatusEnum();

                    if ($license === null) {
                        return [
                            'component' => 'k-form-dialog',
                            'props' => [
                                'fields' => [
                                    'info' => [
                                        'type' => 'info',
                                        'theme' => 'negative',
                                        'text' => I18n::translate('kirby-tools.license.info.notFound')
                                    ]
                                ],
                                'cancelButton' => false,
                                'submitButton' => [
                                    'icon' => 'open',
                                    'text' => I18n::translate('kirby-tools.license.info.hub'),
                                    'theme' => 'info',
                                    'link' => 'https://hub.kirby.tools',
                                    'target' => '_blank'
                                ]
                            ]
                        ];
                    }

                    $versions = LicenseUtils::formatCompatibility($license['compatibility']);
                    $statusText = match ($status) {
                        LicenseStatus::Upgradeable => I18n::translate('kirby-tools.license.info.status.upgradeable'),
                        LicenseStatus::Incompatible => I18n::translate('kirby-tools.license.info.status.incompatible'),
                        default => I18n::translate('kirby-tools.license.info.status.active')
                    };
                    $statusTheme = match ($status) {
                        LicenseStatus::Upgradeable => 'notice',
                        LicenseStatus::Incompatible => 'negative',
                        default => 'positive'
                    };

                    $submitButton = $status === LicenseStatus::Upgradeable ?
                        [
                            'icon' => 'open',
                            'text' => I18n::translate('kirby-tools.license.info.upgrade'),
                            'theme' => 'love',
                            'link' => 'https://kirby.tools/' . $pluginId . '/buy',
                            'target' => '_blank'
                        ] : [
                            'icon' => 'open',
                            'text' => I18n::translate('kirby-tools.license.info.hub'),
                            'theme' => 'info',
                            'link' => 'https://hub.kirby.tools',
                            'target' => '_blank'
                        ];

                    $pluginVersion = LicenseUtils::getPluginVersion($packageName) ?? '–';

                    return [
                        'component' => 'k-form-dialog',
                        'props' => [
                            'size' => 'large',
                            'fields' => [
                                'stats' => [
                                    'type' => 'stats',
                                    'label' => $pluginLabel,
                                    'size' => 'small',
                                    'reports' => [
                                        [
                                            'label' => I18n::translate('kirby-tools.license.info.key'),
                                            'value' => $license['key'],
                                            'icon' => 'key',
                                            'info' => $statusText,
                                            'theme' => $statusTheme
                                        ],
                                        [
                                            'label' => I18n::translate('kirby-tools.license.info.version'),
                                            'value' => 'v' . $pluginVersion,
                                            'icon' => 'tag',
                                            'info' => I18n::template('kirby-tools.license.info.licenseCompatibility', ['versions' => $versions]),
                                            'link' => 'https://kirby.tools/' . $pluginId . '/changelog',
                                            'target' => '_blank'
                                        ]
                                    ]
                                ]
                            ],
                            'submitButton' => $submitButton
                        ]
                    ];
                }
            ],

            // Reached from `PluginLicense::toKirbyStatus` for inactive and invalid licenses.
            "{$dialogPrefix}/activate" => [
                'load' => function () use ($packageName) {
                    $info = [
                        'type' => 'info',
                        'text' => I18n::translate('kirby-tools.license.activate.info')
                    ];

                    // A license file that exists but cannot be parsed leaves the
                    // status at `inactive`, which reads as "never activated".
                    if ((new Licenses($packageName))->getReadError() !== null) {
                        $info = [
                            'type' => 'info',
                            'theme' => 'negative',
                            'text' => I18n::translate('kirby-tools.license.activate.unreadable')
                        ];
                    }

                    return [
                        'component' => 'k-form-dialog',
                        'props' => [
                            'fields' => [
                                'info' => $info,
                                'email' => [
                                    'label' => I18n::translate('kirby-tools.license.activate.email'),
                                    'type' => 'email',
                                    'required' => true
                                ],
                                'licenseKey' => [
                                    'label' => I18n::translate('kirby-tools.license.activate.licenseKey'),
                                    'type' => 'text',
                                    'required' => true,
                                    'help' => I18n::translate('kirby-tools.license.activate.licenseKey.help')
                                ]
                            ],
                            'submitButton' => [
                                'icon' => 'check',
                                'text' => I18n::translate('kirby-tools.license.activate.submit'),
                                'theme' => 'love'
                            ]
                        ]
                    ];
                },
                'submit' => function () use ($packageName) {
                    try {
                        $licenses = Licenses::read($packageName);
                        $licenses->activateFromRequest();
                    } catch (Throwable $e) {
                        throw LicensePanel::activationFailure($e, $packageName);
                    }

                    return [
                        'redirect' => 'system'
                    ];
                }
            ]
        ];

        return array_map(
            fn (array $dialog): array => array_map(self::repairingTranslationCache(...), $dialog),
            $dialogs
        );
    }

    public static function statusLabel(LicenseStatus $status): string
    {
        self::repairTranslationCache();

        return self::resolveTranslation('kirby-tools.license.status.' . $status->value, $status->value);
    }

    /**
     * Translates an activation failure for the Panel, keeping the cause attached.
     *
     * `details` is not debug-gated, so it carries only the package and the
     * cause's key, never the licensing API's response body.
     */
    public static function activationFailure(Throwable $e, string $packageName): InvalidArgumentException
    {
        $message = $e->getMessage();
        $translationKey = self::ACTIVATION_ERROR_KEYS[$message] ?? null;

        // `message` would make Kirby 5's constructor return before it stores
        // `previous`, so the text goes in as an untranslated fallback instead.
        // TODO: Drop K4 compat in v1 – use named arguments once Kirby 5 is the floor.
        return new InvalidArgumentException([
            'fallback' => $translationKey !== null ? self::resolveTranslation($translationKey, $message) : $message,
            'details' => [
                'package' => $packageName,
                'cause' => $e->getCode() ?: $e::class
            ],
            'previous' => $e,
            'translate' => false
        ]);
    }

    /**
     * Drops the translation cache when Kirby filled it before the plugins
     * registered their strings. Public because the wrapper above runs under
     * Kirby's scope, where `self::` no longer means this class.
     */
    public static function repairTranslationCache(): void
    {
        $kirby = App::instance(null, true);

        if ($kirby === null) {
            return;
        }

        if (self::$checkedApp !== $kirby) {
            self::$checkedApp = $kirby;
            self::$checkedLocales = [];
        }

        $locale = I18n::locale();

        if (isset(self::$checkedLocales[$locale]) === true) {
            return;
        }

        self::$checkedLocales[$locale] = true;

        $key = 'kirby-tools.license.status.' . LicenseStatus::Active->value;

        // A stale cache is invisible to `I18n::translate()`, which falls through
        // to the fallback locales; `en` terminates that chain, so it is probed too.
        if (isset(I18n::translation($locale)[$key]) === false ||
            isset(I18n::translation('en')[$key]) === false) {
            unset(I18n::$translations[$locale], I18n::$translations['en']);
        }
    }

    public static function translations(): array
    {
        // Kirby ships no bare `es` translation.
        $spanish = [
            'kirby-tools.license.status.active' => 'Con licencia',
            'kirby-tools.license.status.inactive' => 'Activar ahora',
            'kirby-tools.license.status.invalid' => 'Licencia inválida',
            'kirby-tools.license.status.incompatible' => 'Versión de licencia incompatible',
            'kirby-tools.license.status.upgradeable' => 'Actualización de licencia disponible',

            'kirby-tools.license.activate.info' => 'Introduce los datos de tu licencia para activar el plugin.',
            'kirby-tools.license.activate.unreadable' => 'El archivo de licencia existe pero no se pudo leer. Una nueva activación lo reemplaza, incluidas las licencias de otros plugins que contenga.',
            'kirby-tools.license.activate.email' => 'Correo electrónico',
            'kirby-tools.license.activate.licenseKey' => 'Clave de licencia',
            'kirby-tools.license.activate.licenseKey.help' => 'Encuentra tu clave de licencia en el correo de confirmación de pedido o en <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
            'kirby-tools.license.activate.submit' => 'Activar licencia',

            'kirby-tools.license.info.key' => 'Clave de licencia',
            'kirby-tools.license.info.version' => 'Versión',
            'kirby-tools.license.info.licenseCompatibility' => 'La licencia cubre {versions}',
            'kirby-tools.license.info.status' => 'Estado',
            'kirby-tools.license.info.status.active' => 'Activa',
            'kirby-tools.license.info.status.upgradeable' => 'Actualización disponible',
            'kirby-tools.license.info.status.incompatible' => 'No compatible con la versión instalada',
            'kirby-tools.license.info.notFound' => 'No se encontró ninguna licencia.',
            'kirby-tools.license.info.upgrade' => 'Actualizar licencia',
            'kirby-tools.license.info.hub' => 'Gestionar licencias',

            'kirby-tools.license.error.invalidCredentials' => 'Correo electrónico o clave de licencia incorrecta',
            'kirby-tools.license.error.alreadyActivated' => 'Licencia ya activada',
            'kirby-tools.license.error.invalid' => 'Licencia no válida para este plugin',
            'kirby-tools.license.error.incompatible' => 'Licencia no válida para esta versión del plugin',
            'kirby-tools.license.error.upgradeable' => 'Licencia no válida para esta versión del plugin. Por favor, actualiza tu licencia.'
        ];

        return [
            'en' => [
                'kirby-tools.license.status.active' => 'Licensed',
                'kirby-tools.license.status.inactive' => 'Activate now',
                'kirby-tools.license.status.invalid' => 'Invalid license',
                'kirby-tools.license.status.incompatible' => 'Incompatible license version',
                'kirby-tools.license.status.upgradeable' => 'License upgrade available',

                'kirby-tools.license.activate.info' => 'Enter your license details to activate the plugin.',
                'kirby-tools.license.activate.unreadable' => 'The license file exists but could not be read. Reactivating replaces it, including any licenses it holds for other plugins.',
                'kirby-tools.license.activate.email' => 'Email',
                'kirby-tools.license.activate.licenseKey' => 'License Key',
                'kirby-tools.license.activate.licenseKey.help' => 'Find your license key in your order confirmation email or at <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Activate License',

                'kirby-tools.license.info.key' => 'License Key',
                'kirby-tools.license.info.version' => 'Version',
                'kirby-tools.license.info.licenseCompatibility' => 'License covers {versions}',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Active',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade available',
                'kirby-tools.license.info.status.incompatible' => 'Not compatible with installed version',
                'kirby-tools.license.info.notFound' => 'No license found.',
                'kirby-tools.license.info.upgrade' => 'Upgrade License',
                'kirby-tools.license.info.hub' => 'Manage Licenses',

                'kirby-tools.license.error.invalidCredentials' => 'Email address or license key is incorrect',
                'kirby-tools.license.error.alreadyActivated' => 'License already activated',
                'kirby-tools.license.error.invalid' => 'License not valid for this plugin',
                'kirby-tools.license.error.incompatible' => 'License not valid for this plugin version',
                'kirby-tools.license.error.upgradeable' => 'License not valid for this plugin version. Please upgrade your license.'
            ],
            'de' => [
                'kirby-tools.license.status.active' => 'Lizenziert',
                'kirby-tools.license.status.inactive' => 'Jetzt aktivieren',
                'kirby-tools.license.status.invalid' => 'Ungültige Lizenz',
                'kirby-tools.license.status.incompatible' => 'Inkompatible Lizenzversion',
                'kirby-tools.license.status.upgradeable' => 'Lizenz-Upgrade verfügbar',

                'kirby-tools.license.activate.info' => 'Gib deine Lizenzdaten ein, um das Plugin zu aktivieren.',
                'kirby-tools.license.activate.unreadable' => 'Die Lizenzdatei existiert, konnte aber nicht gelesen werden. Eine erneute Aktivierung ersetzt sie samt aller darin gespeicherten Lizenzen anderer Plugins.',
                'kirby-tools.license.activate.email' => 'E-Mail',
                'kirby-tools.license.activate.licenseKey' => 'Lizenzschlüssel',
                'kirby-tools.license.activate.licenseKey.help' => 'Den Lizenzschlüssel findest du in deiner Bestellbestätigung per E-Mail oder auf <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Lizenz aktivieren',

                'kirby-tools.license.info.key' => 'Lizenzschlüssel',
                'kirby-tools.license.info.version' => 'Version',
                'kirby-tools.license.info.licenseCompatibility' => 'Lizenz gilt für {versions}',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Aktiv',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade verfügbar',
                'kirby-tools.license.info.status.incompatible' => 'Nicht kompatibel mit installierter Version',
                'kirby-tools.license.info.notFound' => 'Keine Lizenz gefunden.',
                'kirby-tools.license.info.upgrade' => 'Lizenz upgraden',
                'kirby-tools.license.info.hub' => 'Lizenzen verwalten',

                'kirby-tools.license.error.invalidCredentials' => 'E-Mail-Adresse oder Lizenzschlüssel ist falsch',
                'kirby-tools.license.error.alreadyActivated' => 'Lizenz bereits aktiviert',
                'kirby-tools.license.error.invalid' => 'Lizenz ungültig für dieses Plugin',
                'kirby-tools.license.error.incompatible' => 'Lizenz ungültig für diese Plugin-Version',
                'kirby-tools.license.error.upgradeable' => 'Lizenz ungültig für diese Plugin-Version. Bitte Lizenz upgraden.'
            ],
            'fr' => [
                'kirby-tools.license.status.active' => 'Sous licence',
                'kirby-tools.license.status.inactive' => 'Activer maintenant',
                'kirby-tools.license.status.invalid' => 'Licence invalide',
                'kirby-tools.license.status.incompatible' => 'Version de licence incompatible',
                'kirby-tools.license.status.upgradeable' => 'Mise à niveau de licence disponible',

                'kirby-tools.license.activate.info' => 'Entrez vos informations de licence pour activer le plugin.',
                'kirby-tools.license.activate.unreadable' => 'Le fichier de licence existe mais n\'a pas pu être lu. Une nouvelle activation le remplace, y compris les licences d\'autres plugins qu\'il contient.',
                'kirby-tools.license.activate.email' => 'E-mail',
                'kirby-tools.license.activate.licenseKey' => 'Clé de licence',
                'kirby-tools.license.activate.licenseKey.help' => 'Retrouvez votre clé de licence dans votre e-mail de confirmation de commande ou sur <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Activer la licence',

                'kirby-tools.license.info.key' => 'Clé de licence',
                'kirby-tools.license.info.version' => 'Version',
                'kirby-tools.license.info.licenseCompatibility' => 'La licence couvre {versions}',
                'kirby-tools.license.info.status' => 'Statut',
                'kirby-tools.license.info.status.active' => 'Active',
                'kirby-tools.license.info.status.upgradeable' => 'Mise à niveau disponible',
                'kirby-tools.license.info.status.incompatible' => 'Non compatible avec la version installée',
                'kirby-tools.license.info.notFound' => 'Aucune licence trouvée.',
                'kirby-tools.license.info.upgrade' => 'Mettre à niveau la licence',
                'kirby-tools.license.info.hub' => 'Gérer les licences',

                'kirby-tools.license.error.invalidCredentials' => 'Adresse e-mail ou clé de licence incorrecte',
                'kirby-tools.license.error.alreadyActivated' => 'Licence déjà activée',
                'kirby-tools.license.error.invalid' => 'Licence invalide pour ce plugin',
                'kirby-tools.license.error.incompatible' => 'Licence invalide pour cette version du plugin',
                'kirby-tools.license.error.upgradeable' => 'Licence invalide pour cette version du plugin. Veuillez mettre à niveau votre licence.'
            ],
            'nl' => [
                'kirby-tools.license.status.active' => 'Gelicentieerd',
                'kirby-tools.license.status.inactive' => 'Nu activeren',
                'kirby-tools.license.status.invalid' => 'Ongeldige licentie',
                'kirby-tools.license.status.incompatible' => 'Incompatibele licentieversie',
                'kirby-tools.license.status.upgradeable' => 'Licentie-upgrade beschikbaar',

                'kirby-tools.license.activate.info' => 'Voer je licentiegegevens in om de plugin te activeren.',
                'kirby-tools.license.activate.unreadable' => 'Het licentiebestand bestaat, maar kon niet worden gelezen. Opnieuw activeren vervangt het, inclusief licenties van andere plugins die erin staan.',
                'kirby-tools.license.activate.email' => 'E-mail',
                'kirby-tools.license.activate.licenseKey' => 'Licentiesleutel',
                'kirby-tools.license.activate.licenseKey.help' => 'Je licentiesleutel vind je in je bestelbevestiging per e-mail of op <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Licentie activeren',

                'kirby-tools.license.info.key' => 'Licentiesleutel',
                'kirby-tools.license.info.version' => 'Versie',
                'kirby-tools.license.info.licenseCompatibility' => 'Licentie geldt voor {versions}',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Actief',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade beschikbaar',
                'kirby-tools.license.info.status.incompatible' => 'Niet compatibel met geïnstalleerde versie',
                'kirby-tools.license.info.notFound' => 'Geen licentie gevonden.',
                'kirby-tools.license.info.upgrade' => 'Licentie upgraden',
                'kirby-tools.license.info.hub' => 'Licenties beheren',

                'kirby-tools.license.error.invalidCredentials' => 'E-mailadres of licentiesleutel is onjuist',
                'kirby-tools.license.error.alreadyActivated' => 'Licentie al geactiveerd',
                'kirby-tools.license.error.invalid' => 'Licentie ongeldig voor deze plugin',
                'kirby-tools.license.error.incompatible' => 'Licentie ongeldig voor deze pluginversie',
                'kirby-tools.license.error.upgradeable' => 'Licentie ongeldig voor deze pluginversie. Upgrade je licentie.'
            ],
            'it' => [
                'kirby-tools.license.status.active' => 'Con licenza',
                'kirby-tools.license.status.inactive' => 'Attiva ora',
                'kirby-tools.license.status.invalid' => 'Licenza non valida',
                'kirby-tools.license.status.incompatible' => 'Versione licenza incompatibile',
                'kirby-tools.license.status.upgradeable' => 'Aggiornamento licenza disponibile',

                'kirby-tools.license.activate.info' => 'Inserisci i dati della tua licenza per attivare il plugin.',
                'kirby-tools.license.activate.unreadable' => 'Il file di licenza esiste ma non è stato possibile leggerlo. Una nuova attivazione lo sostituisce, incluse le licenze di altri plugin che contiene.',
                'kirby-tools.license.activate.email' => 'Email',
                'kirby-tools.license.activate.licenseKey' => 'Chiave di licenza',
                'kirby-tools.license.activate.licenseKey.help' => 'Trova la tua chiave di licenza nell\'e-mail di conferma dell\'ordine o su <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Attiva licenza',

                'kirby-tools.license.info.key' => 'Chiave di licenza',
                'kirby-tools.license.info.version' => 'Versione',
                'kirby-tools.license.info.licenseCompatibility' => 'La licenza copre {versions}',
                'kirby-tools.license.info.status' => 'Stato',
                'kirby-tools.license.info.status.active' => 'Attiva',
                'kirby-tools.license.info.status.upgradeable' => 'Aggiornamento disponibile',
                'kirby-tools.license.info.status.incompatible' => 'Non compatibile con la versione installata',
                'kirby-tools.license.info.notFound' => 'Nessuna licenza trovata.',
                'kirby-tools.license.info.upgrade' => 'Aggiorna licenza',
                'kirby-tools.license.info.hub' => 'Gestisci licenze',

                'kirby-tools.license.error.invalidCredentials' => 'Indirizzo email o chiave di licenza non corretta',
                'kirby-tools.license.error.alreadyActivated' => 'Licenza già attivata',
                'kirby-tools.license.error.invalid' => 'Licenza non valida per questo plugin',
                'kirby-tools.license.error.incompatible' => 'Licenza non valida per questa versione del plugin',
                'kirby-tools.license.error.upgradeable' => 'Licenza non valida per questa versione del plugin. Aggiorna la tua licenza.'
            ],
            'pt_PT' => [
                'kirby-tools.license.status.active' => 'Licenciado',
                'kirby-tools.license.status.inactive' => 'Ativar agora',
                'kirby-tools.license.status.invalid' => 'Licença inválida',
                'kirby-tools.license.status.incompatible' => 'Versão de licença incompatível',
                'kirby-tools.license.status.upgradeable' => 'Atualização de licença disponível',

                'kirby-tools.license.activate.info' => 'Introduza os dados da sua licença para ativar o plugin.',
                'kirby-tools.license.activate.unreadable' => 'O ficheiro de licença existe mas não foi possível lê-lo. Uma nova ativação substitui-o, incluindo quaisquer licenças de outros plugins que contenha.',
                'kirby-tools.license.activate.email' => 'E-mail',
                'kirby-tools.license.activate.licenseKey' => 'Chave de licença',
                'kirby-tools.license.activate.licenseKey.help' => 'Encontre a sua chave de licença no e-mail de confirmação da encomenda ou em <a href="https://hub.kirby.tools" target="_blank">hub.kirby.tools</a>.',
                'kirby-tools.license.activate.submit' => 'Ativar licença',

                'kirby-tools.license.info.key' => 'Chave de licença',
                'kirby-tools.license.info.version' => 'Versão',
                'kirby-tools.license.info.licenseCompatibility' => 'A licença abrange {versions}',
                'kirby-tools.license.info.status' => 'Estado',
                'kirby-tools.license.info.status.active' => 'Ativa',
                'kirby-tools.license.info.status.upgradeable' => 'Atualização disponível',
                'kirby-tools.license.info.status.incompatible' => 'Não compatível com a versão instalada',
                'kirby-tools.license.info.notFound' => 'Nenhuma licença encontrada.',
                'kirby-tools.license.info.upgrade' => 'Atualizar licença',
                'kirby-tools.license.info.hub' => 'Gerir licenças',

                'kirby-tools.license.error.invalidCredentials' => 'Endereço de e-mail ou chave de licença incorretos',
                'kirby-tools.license.error.alreadyActivated' => 'Licença já ativada',
                'kirby-tools.license.error.invalid' => 'Licença não válida para este plugin',
                'kirby-tools.license.error.incompatible' => 'Licença não válida para esta versão do plugin',
                'kirby-tools.license.error.upgradeable' => 'Licença não válida para esta versão do plugin. Por favor, atualize a sua licença.'
            ],
            'es' => $spanish,
            'es_ES' => $spanish,
            'es_419' => $spanish
        ];
    }

    /**
     * Wraps a Panel handler so the translation cache is repaired before it runs.
     *
     * Kirby invokes handlers through `Closure::call()`, which rebinds their scope;
     * the wrapper hands that same scope on, so the handler runs as it would
     * without it.
     */
    private static function repairingTranslationCache(Closure $handler): Closure
    {
        return function (...$arguments) use ($handler) {
            LicensePanel::repairTranslationCache();

            return $handler->call($this, ...$arguments);
        };
    }

    /**
     * Resolves a plugin string, with the bundled English table as the last resort.
     *
     * `I18n::translate()` answers `null` for a key the current locale never
     * loaded, which Kirby turns into its own placeholder message.
     */
    private static function resolveTranslation(string $key, string $fallback): string
    {
        $label = I18n::translate($key);

        return is_string($label) ? $label : (self::translations()['en'][$key] ?? $fallback);
    }
}
