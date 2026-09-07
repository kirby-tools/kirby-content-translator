[![Kirby Content Translator](https://kirby.tools/social-card/content-translator.png)](https://kirby.tools/content-translator)

# Kirby Content Translator

Kirby Content Translator is a plugin for [Kirby CMS](https://getkirby.com) that adds a translation button to any page, file, or site blueprint. Translate with [DeepL](https://www.deepl.com) or [Kirby Copilot](https://kirby.tools/copilot) for AI – the same pipeline runs from PHP and the CLI for hooks, custom controllers, and batch jobs.

## Features

- 🖱️ **One-Click Translation**: View button in any page, file, or site blueprint.
- 📦 **Batch Translation**: All target languages from one dialog, with DeepL or AI.
- 📊 **Translation Coverage**: Rings per language and the pages still missing one, in the Languages view.
- 🧩 **Nested Fields**: Walks blocks, layouts, structures, and objects – `translate: false` holds at any depth.
- 🏷️ **KirbyTags**: Tag attributes translated selectively, URLs and UUIDs preserved.
- 🔁 **Import from Source**: Copy default-language content without translating.
- 🎛️ **Configurable Scope**: By field type, or include and exclude fields, globally or per section.
- ⚡ **PHP API**: Drive translations from CLI or custom workflows, with `before`, `after`, and `warning` hooks – see [PHP classes](https://kirby.tools/docs/content-translator/php-classes).
- 🔌 **Pluggable Strategies**: DeepL, [AI via Copilot](https://kirby.tools/docs/content-translator/providers/ai-translation), or your own [custom strategy](https://kirby.tools/docs/content-translator/providers/custom-translator).

## Licensing

Kirby Content Translator is a commercial plugin that requires a license. You can install and test the plugin locally without a license. However, production environments require a valid license. You can purchase a license from the [Kirby Content Translator Website](https://kirby.tools/content-translator/buy).

## Requirements

- Kirby 4 or Kirby 5

## Installation

### Composer (Recommended)

```bash
composer require johannschopplich/kirby-content-translator
```

### Manual Installation

Download and copy this repository to `/site/plugins/kirby-content-translator`.

## Documentation

For installation, configuration, and usage, see the [Kirby Content Translator documentation](https://kirby.tools/docs/content-translator).

## Support and Questions

We are committed to support you if you have any questions or issues with Kirby Content Translator. There are several ways to get support:

- **GitHub Discussions**: Join the community and engage in discussions on our [GitHub Discussions page](https://github.com/kirby-tools/community/discussions).
- **Email Support**: You can ask questions and seek assistance by emailing us at [hello@kirby.tools](mailto:hello@kirby.tools). Please use the GitHub discussions if you have a general question or comment about Kirby Content Translator.
- **GitHub Issues**: For reporting bugs or requesting new features, please use the [GitHub Issues page](https://github.com/kirby-tools/community/issues).

We encourage you to use the resources above to connect with us and other users of Kirby Content Translator.

For the sake of reproducible bug reports, please include the following information in your bug reports:

- Kirby & Kirby Content Translator version
- Browser environment (name, version, operating system)
- Global and section configuration (without any sensitive information)
- Steps to reproduce the bug (if no reproduction is provided)
- Screenshots or screen recordings if applicable

> [!WARNING]
> Please ensure to **never** include any API keys or other sensitive information in bug reports, feature requests, or any other public communication channel.

## Feedback

We value your feedback and ideas for improving Kirby Content Translator. If you have any suggestions, please feel free to reach out to us via email or preferably by creating a new discussion on our [GitHub Discussions page](https://github.com/kirby-tools/community/discussions).

## License

[Kirby Tools License](./LICENSE.md) © 2024-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
