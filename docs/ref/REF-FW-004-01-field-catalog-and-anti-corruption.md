---
ref: REF-FW-004-01
adr: FW-004
title: 'Field Type Catalog, Condition Operators & Anti-Corruption Enforcement'
lang: en
---

# REF-FW-004-01: Field Type Catalog, Condition Operators & Anti-Corruption Enforcement

> Detail supporting [FW-004](../decisions/FW-004-form-renderer-adapters-field-type-dsl.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-805 + ref-805, ADR-806 + ref-806).

## Renderer interface (legacy shape)

```php
interface form_renderer_interface
{
    public static function target(): render_target; // enum: MFORM, INERTIA
    public function render(form_interface $form): renderer_output;
}
```

Legacy MVP adapters: `mform_renderer` (`framework/moodle/form/`, HTML output via `MoodleQuickForm`, the default for a host-page controller) and `inertia_renderer` (`framework/infrastructure/form/renderer/`, array output `{schema, values, errors, meta}`, default on Inertia routes). Both `@internal`. Default-with-override resolution: default per controller (`render_form($form)`), override per call (`render_form($form, target: render_target::INERTIA)`) — no global per-extension config, no per-user toggle in the MVP.

## The anti-corruption rule (unchanged in spirit, re-homed in practice)

No mform type (`MoodleQuickForm`, `moodleform`, `HTML_QuickForm_*`, `addElement`, `setType`, `setDefault`) may appear outside the mform renderer file. In the legacy plugin this was enforced by a grep-based check plus a custom PHPStan rule forbidding a `moodleform` import in `extensions/`, `base/`, `facade/`, `framework/contract/`, `framework/domain/`. The mechanism generalises cleanly to the standalone package: **no host-specific rendering type may appear inside `Form/` at all** — since the framework no longer ships an mform renderer, the rule is trivially satisfied today by there being no mform code in this repo to police; a host adapter that adds its own renderer inherits the responsibility of keeping host types inside its own renderer file, not leaking them upstream into the shared DSL.

**Renderer never validates** — it only consumes the form's already-computed state (including errors); validation happens exclusively in `form`/`form_request` (FW-003).

## Field type catalog — legacy MVP vs current `FieldFactory`

20 MVP types plus two layout primitives (`section::of()`, `group::of()`) were the closed catalog in ADR-806. Explicitly out of MVP scope at the time: `editor` (rich text), `repeats`, `filemanager` (multi-file), `tags`, `colorpicker`.

| Legacy MVP type (ADR-806) | Current `FieldFactory` method        | Current backing class                    |
|---------------------------|--------------------------------------|------------------------------------------|
| `TEXT`                    | `text()`                             | `TextField`                              |
| `TEXTAREA`                | `textarea()`                         | `TextareaField`                          |
| `PASSWORD`                | `password()`                         | `TextField` (`FieldType::Password`)      |
| `EMAIL`                   | `email()`                            | `TextField` (`FieldType::Email`)         |
| `URL`                     | `url()`                              | `TextField` (`FieldType::Url`)           |
| `INT`                     | `integer()` (renamed from `int()`)   | `IntField`                               |
| `FLOAT`                   | `decimal()` (renamed from `float()`) | `FloatField`                             |
| `SELECT`                  | `select()`                           | `SelectField`                            |
| `MULTISELECT`             | `multiselect()`                      | `SelectField` (`FieldType::Multiselect`) |
| `RADIO`                   | `radio()`                            | `RadioField`                             |
| `CHECKBOX`                | `checkbox()`                         | `GenericField` (`FieldType::Checkbox`)   |
| `SWITCH`                  | `toggle()`                           | `GenericField` (`FieldType::Switch`)     |
| `DATE`                    | `date()`                             | `DateField`                              |
| `DATETIME`                | `datetime()`                         | `DateField` (`FieldType::Datetime`)      |
| `DURATION`                | `duration()`                         | `DurationField`                          |
| `FILE`                    | `file()`                             | `FileField`                              |
| `ENTITY_PICKER`           | `entityPicker()`                     | `EntityPickerField`                      |
| `HIDDEN`                  | `hidden()`                           | `GenericField` (`FieldType::Hidden`)     |
| `STATIC`                  | not confirmed in this pass           | —                                        |
| `HEADER`                  | `header()`                           | `StaticField` (`FieldType::Header`)      |

Structural change worth flagging on its own: `FieldType` — the enum distinguishing variants like `Email`/`Password`/`Url` within one `TextField` class — now lives in **`Middag\Ui\Shared\Enum\FieldType`**, i.e. in `middag-io/ui`, not in this framework. Field-shape vocabulary is shared cross-repo so the UI package's block/page builders speak the same type language as the framework's form DSL without depending on `Form/` internals.

## The three-way naming discrepancy (now resolved by the real code — for a fourth name)

The legacy vault itself flagged a discrepancy: ADR-806 (canonical) defined `field::int()`/`field::float()`; ref-802's example used `field::number()`, a type ADR-806 does not define at all. Neither survived: the current `FieldFactory` uses **`integer()`/`decimal()`**, a naming choice matching neither historical document. Treat this as closed — `integer()`/`decimal()` is the real, current, `@api` surface; `int()`, `float()` and `number()` are all historical names with no live code behind them.

| Concept                 | ADR-806 (canonical, legacy) | ref-802 example (stale, legacy)           | Current `FieldFactory`                                                                               |
|-------------------------|-----------------------------|-------------------------------------------|------------------------------------------------------------------------------------------------------|
| Integer field           | `field::int()`              | `field::number()` (not in ADR-806 at all) | `FieldFactory::integer()`                                                                            |
| Float field             | `field::float()`            | `field::number()` (not in ADR-806 at all) | `FieldFactory::decimal()`                                                                            |
| Pattern/regex condition | `->pattern(regex)`          | `->regex('/pattern/')`                    | not confirmed in this pass — check `ConditionEvaluator`/`AbstractField` before restating either name |

## Naming/validation rules

snake_case-equivalent naming validated at field construction; reserved names `id`, `submit`, `cancel`, `save`, `_token` throw `InvalidArgumentException`; there is no `->optional()` — absence of `->required()` already means optional; `checkbox` returns `0|1`, `switch`/`toggle` returns `bool`. The construction-time reserved-name and pattern validation is confirmed live in the current `FieldFactory`/`AbstractField` docblocks.

## Condition operators (legacy, mform-incompatible subset)

`matches` (Inertia-only), non-string comparisons on string fields, and compound AND/OR multi-field conditions degrade to a `disabledIf`-style callback or server-side post-submit validation on the mform target, with a runtime warning. This entire caveat is now moot for this repo specifically (no mform renderer ships here) but remains relevant guidance for whichever adapter builds a host-specific renderer that must degrade the same conditions gracefully.

## The "densest part of the project" risk

The legacy ADR-805 named the full `MoodleQuickForm` field-type × modifier mapping as the area most likely to have its effort underestimated. That risk has moved wholesale to the adapter tier: this framework repo carries no such mapping today, so any team building a Moodle (or other host) renderer adapter should budget for it explicitly rather than assume the framework already solved it.

## Anti-pattern (legacy, still applicable)

Duplicating a validation rule across the field schema and an attached `form_request` (see FW-003's anti-pattern note) applies equally here when the duplicated rule concerns rendering-affecting constraints (e.g. `max:n` shown as a client hint) — keep the schema as the single declaration site.
