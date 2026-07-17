---
ref: REF-FW-003-01
adr: FW-003
title: 'Form Lifecycle, Validation Rule Catalog & Legacy Migration Notes'
lang: en
---

# REF-FW-003-01: Form Lifecycle, Validation Rule Catalog & Legacy Migration Notes

> Detail supporting [FW-003](../decisions/FW-003-schema-first-forms-form-request-escalation.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-802 + ref-802).

## Schema example (legacy shape, still representative)

```php
final class site_form extends form
{
    public function schema(): array
    {
        return [
            field::text('name')->label('site.name')->required()->max(255),
            field::url('url')->label('site.url')->required(),
            field::select('provider')->label('site.provider')
                ->options(['eduzz' => 'provider.eduzz', 'woocommerce' => 'provider.woocommerce'])
                ->required(),
            field::password('api_key')->label('site.api_key')
                ->visible_when('provider', 'in', ['eduzz'])
                ->required_when('provider', 'in', ['eduzz']),
        ];
    }
}
```

## Escalation via `REQUEST`

```php
final class site_form extends form
{
    public const REQUEST = site_store_request::class;
    public function schema(): array { /* structure/UI only */ }
}
```

`form_request` is used in three scenarios: (1) escalation attached to a form via `REQUEST`; (2) a pure REST API with no UI at all; (3) validation shared between a form and a REST endpoint covering the same payload.

## Lifecycle usage

```php
public function edit(?int $id, site_form $form): Response
{
    if ($form->is_submitted_and_valid()) {
        $this->site_service->save($form->validated());
        return $this->redirect_to_route('ecommerce.sites.index');
    }
    return $this->render_form($form);
}
```

## Built-in validation rule catalog

`required`, `string`, `int`, `float`, `bool`, `email`, `url`, `max:n`, `min:n`, `in:a,b,c`, `regex:pattern`, `nullable`, `array`, `exists:table,column`. The legacy REF noted this catalog was explicitly inspired by Laravel Validation / Respect Validation, and suggested the base implementation "may reuse `abstract_validator` (Respect/Validation) internally" — a provenance detail that did not appear in ADR-802 itself, only in its REF companion. In the current codebase, `Form/FormValidator.php` is the live validator; whether it still wraps Respect/Validation internally or was reimplemented against Symfony Validator (already a framework dependency per `composer.json`) should be confirmed directly in source before restating this provenance claim as current fact.

## i18n rule

Labels, help text, option lists and validation error messages must resolve through the translation contract (a `lang_string`-equivalent lookup by key + component in the legacy vault) — raw strings were forbidden by a custom static-analysis rule in the legacy plugin. The current equivalent is the `Translation/Contract/TranslatorInterface` concern (`FallbackTranslator` as the OSS default) documented in `architecture.md` §5 — the same rule, generalised to a host-neutral contract instead of a Moodle-specific helper.

## Naming/location convention (legacy, host-plugin specific)

Extension forms lived at `extensions/{slug}/{aggregate}/{entity}_form.php`; `form_request` classes at `{action}_request.php`; base classes at `classes/base/form.php` / `classes/base/form_request.php`. This directory convention was specific to the Moodle-plugin composition root and does not carry over literally to the standalone package's `src/Form/` concern-first layout — it is preserved here only as historical context for anyone still reading the legacy plugin's tree.

## Legacy migration policy (historical, largely moot for this repo)

Migration away from the old host form-builder was a soft deprecation with no hard deadline: migrate a form only when it is touched by an unrelated functional change, gated by at least one proof-of-concept form migrated alongside the supporting infrastructure, with the eventually-retired legacy form moved to a `legacy/` folder or removed via a framework-level deprecated-code convention. Since the standalone package never shipped the legacy renderer in the first place, this policy is only relevant to a host adapter (e.g. a Moodle adapter) that still bridges an older form engine — not to `middag-php-framework` itself.

## Anti-pattern (legacy REF, still applicable)

Duplicating a rule in both the schema and the attached `form_request` creates ambiguity about which one wins. The rule is fixed: `form_request` precedes the schema when both declare a rule for the same field — never duplicate.
