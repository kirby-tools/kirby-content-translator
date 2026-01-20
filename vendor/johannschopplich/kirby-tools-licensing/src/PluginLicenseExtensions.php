<?php

declare(strict_types = 1);

namespace JohannSchopplich\Licensing;

use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\I18n;
use Throwable;

/**
 * Provides shared Kirby extensions (dialogs, translations) for Kirby Tools plugin licensing.
 *
 * @link      https://kirby.tools
 * @copyright Johann Schopplich
 * @license   AGPL-3.0
 */
class PluginLicenseExtensions
{
    /**
     * Maps exception messages from license activation to translation keys.
     */
    public const ACTIVATION_ERROR_KEYS = [
        'Unauthorized' => 'kirby-tools.license.error.invalidCredentials',
        'License key already activated' => 'kirby-tools.license.error.alreadyActivated',
        'License key not valid for this plugin' => 'kirby-tools.license.error.invalid',
        'License key not valid for this plugin version' => 'kirby-tools.license.error.incompatible',
        'License key not valid for this plugin version, please upgrade your license' => 'kirby-tools.license.error.upgradeable'
    ];

    public static function api(string $packageName): array
    {
        $apiPrefix = static::toApiPrefix($packageName);

        return [
            [
                'pattern' => "{$apiPrefix}/activate",
                'method' => 'POST',
                'action' => function () use ($packageName) {
                    try {
                        $licenses = Licenses::read($packageName);
                        return $licenses->activateFromRequest();
                    } catch (Throwable $e) {
                        $message = $e->getMessage();
                        $translationKey = PluginLicenseExtensions::ACTIVATION_ERROR_KEYS[$message] ?? null;

                        throw new InvalidArgumentException(
                            $translationKey ? I18n::translate($translationKey) : $message
                        );
                    }
                }
            ]
        ];
    }

    public static function dialogs(string $packageName, string $pluginLabel): array
    {
        $dialogPrefix = static::toPackageSlug($packageName);
        $pluginSlug = static::toPluginId($packageName);

        return [
            // License info dialog (for active/upgradeable/incompatible licenses)
            "{$dialogPrefix}/license" => [
                'load' => function () use ($packageName, $pluginSlug, $pluginLabel) {
                    $licenses = Licenses::read($packageName);
                    $license = $licenses->getLicense();
                    $status = $licenses->getStatus();

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

                    $versions = PluginLicenseExtensions::formatCompatibility($license['compatibility']);
                    $statusText = match ($status) {
                        LicenseStatus::UPGRADEABLE => I18n::translate('kirby-tools.license.info.status.upgradeable'),
                        LicenseStatus::INCOMPATIBLE => I18n::translate('kirby-tools.license.info.status.incompatible'),
                        default => I18n::translate('kirby-tools.license.info.status.active')
                    };
                    $statusTheme = match ($status) {
                        LicenseStatus::UPGRADEABLE => 'notice',
                        LicenseStatus::INCOMPATIBLE => 'negative',
                        default => 'positive'
                    };

                    $submitButton = $status === LicenseStatus::UPGRADEABLE ?
                        [
                            'icon' => 'open',
                            'text' => I18n::translate('kirby-tools.license.info.upgrade'),
                            'theme' => 'love',
                            'link' => 'https://kirby.tools/' . $pluginSlug . '/buy',
                            'target' => '_blank'
                        ] : [
                            'icon' => 'open',
                            'text' => I18n::translate('kirby-tools.license.info.hub'),
                            'theme' => 'info',
                            'link' => 'https://hub.kirby.tools',
                            'target' => '_blank'
                        ];

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
                                            'label' => I18n::translate('kirby-tools.license.info.compatibility'),
                                            'value' => $versions,
                                            'icon' => 'layers'
                                        ]
                                    ]
                                ]
                            ],
                            'submitButton' => $submitButton
                        ]
                    ];
                }
            ],

            // Activation dialog (for inactive/invalid licenses)
            "{$dialogPrefix}/activate" => [
                'load' => function () {
                    return [
                        'component' => 'k-form-dialog',
                        'props' => [
                            'fields' => [
                                'info' => [
                                    'type' => 'info',
                                    'text' => I18n::translate('kirby-tools.license.activate.info')
                                ],
                                'email' => [
                                    'label' => I18n::translate('kirby-tools.license.activate.email'),
                                    'type' => 'email',
                                    'required' => true
                                ],
                                'orderId' => [
                                    'label' => I18n::translate('kirby-tools.license.activate.orderId'),
                                    'type' => 'text',
                                    'required' => true,
                                    'help' => I18n::translate('kirby-tools.license.activate.orderId.help')
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
                        $message = $e->getMessage();
                        $translationKey = PluginLicenseExtensions::ACTIVATION_ERROR_KEYS[$message] ?? null;

                        throw new InvalidArgumentException(
                            $translationKey ? I18n::translate($translationKey) : $message
                        );
                    }

                    return [
                        'redirect' => 'system'
                    ];
                }
            ]
        ];
    }

    public static function translations(): array
    {
        return [
            'en' => [
                'kirby-tools.license.status.active' => 'Licensed',
                'kirby-tools.license.status.inactive' => 'Activate now',
                'kirby-tools.license.status.invalid' => 'Invalid license',
                'kirby-tools.license.status.incompatible' => 'Incompatible license version',
                'kirby-tools.license.status.upgradeable' => 'License upgrade available',

                'kirby-tools.license.activate.info' => 'Enter your license details to activate the plugin.',
                'kirby-tools.license.activate.email' => 'Email',
                'kirby-tools.license.activate.orderId' => 'Order ID',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Find your order number</a> on Lemon Squeezy or <a href="mailto:hello@kirby.tools">contact us</a> if you cannot find it.',
                'kirby-tools.license.activate.submit' => 'Activate License',

                'kirby-tools.license.info.key' => 'License Key',
                'kirby-tools.license.info.compatibility' => 'Compatibility',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Active',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade available',
                'kirby-tools.license.info.status.incompatible' => 'Not compatible with installed version',
                'kirby-tools.license.info.notFound' => 'No license found.',
                'kirby-tools.license.info.upgrade' => 'Upgrade License',
                'kirby-tools.license.info.hub' => 'Manage Licenses',

                'kirby-tools.license.error.invalidCredentials' => 'Email address or order ID is incorrect',
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
                'kirby-tools.license.activate.email' => 'E-Mail',
                'kirby-tools.license.activate.orderId' => 'Bestellnummer',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Finde deine Bestellnummer</a> bei Lemon Squeezy oder <a href="mailto:hello@kirby.tools">kontaktiere uns</a>, wenn du sie nicht finden kannst.',
                'kirby-tools.license.activate.submit' => 'Lizenz aktivieren',

                'kirby-tools.license.info.key' => 'Lizenzschlüssel',
                'kirby-tools.license.info.compatibility' => 'Kompatibilität',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Aktiv',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade verfügbar',
                'kirby-tools.license.info.status.incompatible' => 'Nicht kompatibel mit installierter Version',
                'kirby-tools.license.info.notFound' => 'Keine Lizenz gefunden.',
                'kirby-tools.license.info.upgrade' => 'Lizenz upgraden',
                'kirby-tools.license.info.hub' => 'Lizenzen verwalten',

                'kirby-tools.license.error.invalidCredentials' => 'E-Mail-Adresse oder Bestellnummer ist falsch',
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
                'kirby-tools.license.activate.email' => 'E-mail',
                'kirby-tools.license.activate.orderId' => 'Numéro de commande',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Trouvez votre numéro de commande</a> sur Lemon Squeezy ou <a href="mailto:hello@kirby.tools">contactez-nous</a> si vous ne le trouvez pas.',
                'kirby-tools.license.activate.submit' => 'Activer la licence',

                'kirby-tools.license.info.key' => 'Clé de licence',
                'kirby-tools.license.info.compatibility' => 'Compatibilité',
                'kirby-tools.license.info.status' => 'Statut',
                'kirby-tools.license.info.status.active' => 'Active',
                'kirby-tools.license.info.status.upgradeable' => 'Mise à niveau disponible',
                'kirby-tools.license.info.status.incompatible' => 'Non compatible avec la version installée',
                'kirby-tools.license.info.notFound' => 'Aucune licence trouvée.',
                'kirby-tools.license.info.upgrade' => 'Mettre à niveau la licence',
                'kirby-tools.license.info.hub' => 'Gérer les licences',

                'kirby-tools.license.error.invalidCredentials' => 'Adresse e-mail ou numéro de commande incorrect',
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
                'kirby-tools.license.activate.email' => 'E-mail',
                'kirby-tools.license.activate.orderId' => 'Bestelnummer',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Vind je bestelnummer</a> op Lemon Squeezy of <a href="mailto:hello@kirby.tools">neem contact met ons op</a> als je het niet kunt vinden.',
                'kirby-tools.license.activate.submit' => 'Licentie activeren',

                'kirby-tools.license.info.key' => 'Licentiesleutel',
                'kirby-tools.license.info.compatibility' => 'Compatibiliteit',
                'kirby-tools.license.info.status' => 'Status',
                'kirby-tools.license.info.status.active' => 'Actief',
                'kirby-tools.license.info.status.upgradeable' => 'Upgrade beschikbaar',
                'kirby-tools.license.info.status.incompatible' => 'Niet compatibel met geïnstalleerde versie',
                'kirby-tools.license.info.notFound' => 'Geen licentie gevonden.',
                'kirby-tools.license.info.upgrade' => 'Licentie upgraden',
                'kirby-tools.license.info.hub' => 'Licenties beheren',

                'kirby-tools.license.error.invalidCredentials' => 'E-mailadres of bestelnummer is onjuist',
                'kirby-tools.license.error.alreadyActivated' => 'Licentie al geactiveerd',
                'kirby-tools.license.error.invalid' => 'Licentie ongeldig voor deze plugin',
                'kirby-tools.license.error.incompatible' => 'Licentie ongeldig voor deze pluginversie',
                'kirby-tools.license.error.upgradeable' => 'Licentie ongeldig voor deze pluginversie. Upgrade je licentie.'
            ],
            'es' => [
                'kirby-tools.license.status.active' => 'Con licencia',
                'kirby-tools.license.status.inactive' => 'Activar ahora',
                'kirby-tools.license.status.invalid' => 'Licencia inválida',
                'kirby-tools.license.status.incompatible' => 'Versión de licencia incompatible',
                'kirby-tools.license.status.upgradeable' => 'Actualización de licencia disponible',

                'kirby-tools.license.activate.info' => 'Introduce los datos de tu licencia para activar el plugin.',
                'kirby-tools.license.activate.email' => 'Correo electrónico',
                'kirby-tools.license.activate.orderId' => 'Número de pedido',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Encuentra tu número de pedido</a> en Lemon Squeezy o <a href="mailto:hello@kirby.tools">contáctanos</a> si no lo encuentras.',
                'kirby-tools.license.activate.submit' => 'Activar licencia',

                'kirby-tools.license.info.key' => 'Clave de licencia',
                'kirby-tools.license.info.compatibility' => 'Compatibilidad',
                'kirby-tools.license.info.status' => 'Estado',
                'kirby-tools.license.info.status.active' => 'Activa',
                'kirby-tools.license.info.status.upgradeable' => 'Actualización disponible',
                'kirby-tools.license.info.status.incompatible' => 'No compatible con la versión instalada',
                'kirby-tools.license.info.notFound' => 'No se encontró ninguna licencia.',
                'kirby-tools.license.info.upgrade' => 'Actualizar licencia',
                'kirby-tools.license.info.hub' => 'Gestionar licencias',

                'kirby-tools.license.error.invalidCredentials' => 'Correo electrónico o número de pedido incorrecto',
                'kirby-tools.license.error.alreadyActivated' => 'Licencia ya activada',
                'kirby-tools.license.error.invalid' => 'Licencia no válida para este plugin',
                'kirby-tools.license.error.incompatible' => 'Licencia no válida para esta versión del plugin',
                'kirby-tools.license.error.upgradeable' => 'Licencia no válida para esta versión del plugin. Por favor, actualiza tu licencia.'
            ],
            'it' => [
                'kirby-tools.license.status.active' => 'Con licenza',
                'kirby-tools.license.status.inactive' => 'Attiva ora',
                'kirby-tools.license.status.invalid' => 'Licenza non valida',
                'kirby-tools.license.status.incompatible' => 'Versione licenza incompatibile',
                'kirby-tools.license.status.upgradeable' => 'Aggiornamento licenza disponibile',

                'kirby-tools.license.activate.info' => 'Inserisci i dati della tua licenza per attivare il plugin.',
                'kirby-tools.license.activate.email' => 'Email',
                'kirby-tools.license.activate.orderId' => 'Numero ordine',
                'kirby-tools.license.activate.orderId.help' => '<a href="https://app.lemonsqueezy.com/my-orders" target="_blank">Trova il tuo numero d\'ordine</a> su Lemon Squeezy o <a href="mailto:hello@kirby.tools">contattaci</a> se non riesci a trovarlo.',
                'kirby-tools.license.activate.submit' => 'Attiva licenza',

                'kirby-tools.license.info.key' => 'Chiave di licenza',
                'kirby-tools.license.info.compatibility' => 'Compatibilità',
                'kirby-tools.license.info.status' => 'Stato',
                'kirby-tools.license.info.status.active' => 'Attiva',
                'kirby-tools.license.info.status.upgradeable' => 'Aggiornamento disponibile',
                'kirby-tools.license.info.status.incompatible' => 'Non compatibile con la versione installata',
                'kirby-tools.license.info.notFound' => 'Nessuna licenza trovata.',
                'kirby-tools.license.info.upgrade' => 'Aggiorna licenza',
                'kirby-tools.license.info.hub' => 'Gestisci licenze',

                'kirby-tools.license.error.invalidCredentials' => 'Indirizzo email o numero ordine non corretto',
                'kirby-tools.license.error.alreadyActivated' => 'Licenza già attivata',
                'kirby-tools.license.error.invalid' => 'Licenza non valida per questo plugin',
                'kirby-tools.license.error.incompatible' => 'Licenza non valida per questa versione del plugin',
                'kirby-tools.license.error.upgradeable' => 'Licenza non valida per questa versione del plugin. Aggiorna la tua licenza.'
            ]
        ];
    }

    /**
     * Converts package name to slug (e.g., `johannschopplich/kirby-copilot` → `johannschopplich-kirby-copilot`)
     */
    public static function toPackageSlug(string $packageName): string
    {
        return str_replace('/', '-', $packageName);
    }

    /**
     * Extracts plugin slug from package name (e.g., `johannschopplich/kirby-copilot` → `copilot`)
     */
    public static function toPluginId(string $packageName): string
    {
        return preg_replace('!^.*/kirby-!', '', $packageName);
    }

    /**
     * Converts package name to API prefix (e.g., `johannschopplich/kirby-copilot` → `__copilot__`)
     */
    public static function toApiPrefix(string $packageName): string
    {
        return '__' . static::toPluginId($packageName) . '__';
    }

    /**
     * Formats a compatibility string like `^1 || ^2 || ^3` into `v1, v2 & v3`.
     */
    public static function formatCompatibility(string $compatibility): string
    {
        $versions = array_map(
            fn ($part) => (int)preg_replace('/\D/', '', trim($part)),
            explode('||', $compatibility)
        );

        $formatted = array_map(fn ($v) => "v{$v}", $versions);

        $count = count($formatted);
        if ($count === 1) {
            return $formatted[0];
        }

        if ($count === 2) {
            return $formatted[0] . ' & ' . $formatted[1];
        }

        $last = array_pop($formatted);
        return implode(', ', $formatted) . ' & ' . $last;
    }
}
