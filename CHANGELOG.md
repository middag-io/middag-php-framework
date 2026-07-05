# Changelog

## [1.1.2](https://github.com/middag-io/middag-php-framework/compare/v1.1.1...v1.1.2) (2026-07-05)


### Miscellaneous

* **deps:** remove unused zircote/swagger-php dev dependency ([a58d194](https://github.com/middag-io/middag-php-framework/commit/a58d1940f12efe554fc199fd321168b834c5bd21))
* release 1.1.2 ([5c4e3b0](https://github.com/middag-io/middag-php-framework/commit/5c4e3b0d64d9739985608c25abe360099d54c8bf))

## [1.1.1](https://github.com/middag-io/middag-php-framework/compare/v1.1.0...v1.1.1) (2026-07-03)


### Miscellaneous

* release 1.1.1 ([a53636b](https://github.com/middag-io/middag-php-framework/commit/a53636bcf1becc9e574c3a6e50f881ccb91fc75f))

## [1.1.0](https://github.com/middag-io/middag-php-framework/compare/v1.0.3...v1.1.0) (2026-07-03)


### Bug Fixes

* **mail:** harden Address validation and quote RFC 5322 display names ([c3b7354](https://github.com/middag-io/middag-php-framework/commit/c3b7354456639feb91152ff415f7554a1466e257))


### Documentation

* **api-stability:** define the 1.x breaking-change policy ([dd26e23](https://github.com/middag-io/middag-php-framework/commit/dd26e237c04c90bc0fc137866c2abb5eb1c84ed0))
* document Observability and Mail concerns ([0996ce1](https://github.com/middag-io/middag-php-framework/commit/0996ce16684b16ae92a975dafb62323f147cae3f))
* drop the last pre-1.0 claims from the security policy and PR template ([b37bf36](https://github.com/middag-io/middag-php-framework/commit/b37bf36a59ae03f3d43370176bf0f280c84c3703))
* **versioning:** gate major releases behind explicit maintainer sign-off ([7594a91](https://github.com/middag-io/middag-php-framework/commit/7594a91cbe570a6f00e640ebd57347581044ceb4))


### Miscellaneous

* **composer:** align coverage scripts with the canonical baseline ([4fc821f](https://github.com/middag-io/middag-php-framework/commit/4fc821f9b1e3fd456c5111c56b6fdb790191ead9))
* **composer:** require symfony/translation-contracts explicitly ([b71d2ce](https://github.com/middag-io/middag-php-framework/commit/b71d2ce2315c9db5426c23176e4d2023cbf8c89a))
* release 1.1.0 ([f022a66](https://github.com/middag-io/middag-php-framework/commit/f022a666caebdafc0f35cd78d34152abfa9e7d27))
* **release:** drop inert pre-major flags from release-please config ([d4ca57b](https://github.com/middag-io/middag-php-framework/commit/d4ca57be6ee7df736c794d4a1d6d2f8b608a6fcd))

## [1.0.3](https://github.com/middag-io/middag-php-framework/compare/v1.0.2...v1.0.3) (2026-07-03)


### Bug Fixes

* **form:** update imports for ui 1.2 namespaces ([2fb9cf4](https://github.com/middag-io/middag-php-framework/commit/2fb9cf4a860e5a27a688ea412057cf1b381caeb7))


### Miscellaneous

* **deps:** require middag-io/ui ^1.2 ([936113d](https://github.com/middag-io/middag-php-framework/commit/936113da2ab25775cf4bd05cc05f744fb46347c7))

## [1.0.2](https://github.com/middag-io/middag-php-framework/compare/v1.0.1...v1.0.2) (2026-07-02)


### ⚠ BREAKING CHANGES

* **mail:** Mail->to/cc/bcc are now list<Address>, from/replyTo ?Address, attachments list<Attachment> (string inputs still accepted and normalised).

### Features

* **filesystem:** flysystem-backed FilesystemInterface adapter ([e3b4691](https://github.com/middag-io/middag-php-framework/commit/e3b46913250a11dc49c4867647f2aa767301157c))
* **mail:** address and attachment value objects with cid embeds ([aaa5f91](https://github.com/middag-io/middag-php-framework/commit/aaa5f91324f4ad0ab1129e9e220acb9dae514ffa))
* **observability:** error reporter port with null and sentry impls ([9ca740a](https://github.com/middag-io/middag-php-framework/commit/9ca740aacdd3fad2bc5b7eabd078d1d1d704384a))


### Documentation

* **changelog:** drop release-please reset noise from 1.0.x sections ([925e341](https://github.com/middag-io/middag-php-framework/commit/925e3415efd577cd70f976e9a5b27fd0b04aa298))


### Miscellaneous

* **composer:** align suggests with shipped code ([e0a044d](https://github.com/middag-io/middag-php-framework/commit/e0a044df0d71d8dacbb3e83073e80bde395d20e4))

## [1.0.1](https://github.com/middag-io/middag-php-framework/compare/v1.0.0...v1.0.1) (2026-06-30)


### Features

* **http:** Inertia v3 server-side bootstrap with configurable app id ([2eaf869](https://github.com/middag-io/middag-php-framework/commit/2eaf86949d27a022473d5c37ecf08f3c613f1e79))


### Miscellaneous

* drop stale Unreleased changelog block, fix host-adapter suggest constraints ([e29d7de](https://github.com/middag-io/middag-php-framework/commit/e29d7defd64d2dd870ec536d17530c1b50407c78))

## [1.0.0](https://github.com/middag-io/middag-php-framework/compare/v0.11.3...v1.0.0) (2026-06-27)


First stable release — promotes the 0.11.x line (concern-first `src/`, PSR-15 HttpKernel with Inertia v3, command bus on symfony/messenger, immutable QueryBuilder, form engine) to the 1.0 API surface. No functional changes over v0.11.3.

## [0.11.3](https://github.com/middag-io/middag-php-framework/compare/v0.11.2...v0.11.3) (2026-06-26)


### Features

* **kernel:** promote core-consumed contracts to [@api](https://github.com/api) surface ([9b57b8d](https://github.com/middag-io/middag-php-framework/commit/9b57b8d56624ad26a2319c57e7307384543862be))


### Documentation

* declare [@api](https://github.com/api) stability policy and freeze core-facing contracts ([924269e](https://github.com/middag-io/middag-php-framework/commit/924269ed7abe53cd825d8a263afc4d720de75567))
* link API-STABILITY from README ([8fc861d](https://github.com/middag-io/middag-php-framework/commit/8fc861da62bb68b98dcce0d5ffe8f190e7771a8d))


### Miscellaneous

* **ci:** update UI dependency resolution note ([402d4a2](https://github.com/middag-io/middag-php-framework/commit/402d4a2cc9071418889756cfa6c11bec8d3a1ee1))
* **ci:** upgrade GitHub Actions ([9831a95](https://github.com/middag-io/middag-php-framework/commit/9831a9519d61898dee1d039fb91043698705bb5e))

## [0.11.2](https://github.com/middag-io/middag-php-framework/compare/v0.11.1...v0.11.2) (2026-06-22)


### Features

* **kernel:** add neutral host component context contract + registry ([ba9e492](https://github.com/middag-io/middag-php-framework/commit/ba9e4923be8e70531d41f5c5920b26430fe2232b))


### Bug Fixes

* **filesystem:** normalize redundant separators in root path ([9f0e674](https://github.com/middag-io/middag-php-framework/commit/9f0e67412073c1674f988dcc79e03722f5aa7398))


### Documentation

* **kernel:** document HostComponentContextInterface bridge contract ([f4e24a2](https://github.com/middag-io/middag-php-framework/commit/f4e24a28c5950da2477fa3f7fdc815becf0a586a))

## [0.11.1](https://github.com/middag-io/middag-php-framework/compare/v0.11.0...v0.11.1) (2026-06-06)


### Features

* **bus:** add attribute-driven command handler resolution ([d8b0518](https://github.com/middag-io/middag-php-framework/commit/d8b05188284f88b60202c6716c2ac6c6f0d57427))


### Refactoring

* **framework:** promote host event bridge seam to public api ([0cdc7d6](https://github.com/middag-io/middag-php-framework/commit/0cdc7d6456bc13290280ec511724e72f36afbc3f))

## [0.11.0](https://github.com/middag-io/middag-php-framework/compare/v0.10.5...v0.11.0) (2026-06-04)


### ⚠ BREAKING CHANGES

* **http:** MiddagValidationException::errors() values and the 422 errors[field] payload change from string|string[] to an object {message,key,domain,params} (a list when a field has several errors).

### Features

* **http:** add ValidationErrorBag (violations to TranslatableMessage map) ([ac77a36](https://github.com/middag-io/middag-php-framework/commit/ac77a367acf36d2a655639ab1007e27e5bbd8cb9))
* **http:** add ValidationErrorSerializer (errors to {message,key,domain,params}) ([be72a04](https://github.com/middag-io/middag-php-framework/commit/be72a04e4d27f13a7a6ec07aa578f285fcbb03bc))
* **http:** resolve validation errors to {message,key,domain,params} on the wire ([29526cf](https://github.com/middag-io/middag-php-framework/commit/29526cf5c410eb115f694eefa0d459e92060c012))
* **translation:** add TranslatableMessage deferred-translation value object ([6012d84](https://github.com/middag-io/middag-php-framework/commit/6012d84c6cb9e74941bee54c7ff4ae55c8bcafbc))
* **translation:** add ViolationKeyMap (constraint violation to short key) ([fff3abc](https://github.com/middag-io/middag-php-framework/commit/fff3abcc5876a55991db5d785bba69d8fc91abdc))
* validation-error i18n contract (deferred-translation) ([cf6dcd0](https://github.com/middag-io/middag-php-framework/commit/cf6dcd0790aa45418c4c3851829ee76126632a66))


### Bug Fixes

* **http:** DtoHydrator routes denormalization errors through the translator ([8fe5d68](https://github.com/middag-io/middag-php-framework/commit/8fe5d68e8f0eccb2de1ed812d49187c3a666efcb))
* **translation:** drop typed class constant so ViolationKeyMap parses on PHP 8.2 ([7066939](https://github.com/middag-io/middag-php-framework/commit/706693929415b7f1e9da06bdcc301a9d1ccf7ad7))


### Refactoring

* **exception:** widen MiddagValidationException errors to TranslatableMessage union ([e301418](https://github.com/middag-io/middag-php-framework/commit/e30141888028ff82ec5f3cfd10b048590d4c517b))
* **http:** AbstractFormRequest emits TranslatableMessage errors, drop in-request translation ([14b7059](https://github.com/middag-io/middag-php-framework/commit/14b70592e41dfcaa7375719c366e82f565f84161))


### Miscellaneous

* ignore docs/superpowers (local agent/brainstorming specs) ([e3a9ea2](https://github.com/middag-io/middag-php-framework/commit/e3a9ea2503f86fbe07349d67956815d65e8a921f))

## [0.10.5](https://github.com/middag-io/middag-php-framework/compare/v0.10.4...v0.10.5) (2026-06-04)


### Bug Fixes

* **http:** coerce blank request input to null (Laravel parity) ([e92465a](https://github.com/middag-io/middag-php-framework/commit/e92465afe638f835e861f08512739144046b0a7b))
* **http:** coerce blank request input to null (Laravel parity) ([d84a33c](https://github.com/middag-io/middag-php-framework/commit/d84a33c204f48a5df18b46719eaf369b363a4fb0))

## [0.10.4](https://github.com/middag-io/middag-php-framework/compare/v0.10.3...v0.10.4) (2026-06-04)


### Features

* **http:** bind typed DTO requests via #[ValidatedDto] ([02cf0ad](https://github.com/middag-io/middag-php-framework/commit/02cf0ad52c6114d8dc0a9cf493df9d78cb4f5e5d))
* **http:** typed DTO request binding via #[ValidatedDto] ([0bbf20f](https://github.com/middag-io/middag-php-framework/commit/0bbf20f239004085585f63865ec76a2ea094d9da))


### Bug Fixes

* **http:** coerce DTO scalars without relying on serializer 8.1 ([15fe2dc](https://github.com/middag-io/middag-php-framework/commit/15fe2dc95ba1937a0352874a5606663a19f7d65f))


### Miscellaneous

* ignore .gstack/ state dir ([44bbc0b](https://github.com/middag-io/middag-php-framework/commit/44bbc0b1c895df8537ebeaf76b11231b7e8a2db7))

## [0.10.3](https://github.com/middag-io/middag-php-framework/compare/v0.10.2...v0.10.3) (2026-06-04)


### Features

* **form:** ship the default EntitySourceRegistry over EntitySourceInterface ([f3d3cf9](https://github.com/middag-io/middag-php-framework/commit/f3d3cf966640f2e4268ac97ba36c3a11412231f5))
* **form:** ship the default EntitySourceRegistry over EntitySourceInterface ([96be563](https://github.com/middag-io/middag-php-framework/commit/96be56386a29d9d24981b5c133a24bd323844107))

## [0.10.2](https://github.com/middag-io/middag-php-framework/compare/v0.10.1...v0.10.2) (2026-06-04)


### Bug Fixes

* **deps:** require the stable middag-io/ui ^1.0 ([12e9118](https://github.com/middag-io/middag-php-framework/commit/12e9118c4eada929cca71f31860427b59f1b4840))
* **deps:** require the stable middag-io/ui ^1.0 ([60ebe39](https://github.com/middag-io/middag-php-framework/commit/60ebe39191f0779610719439f92b5784742351ae))

## [0.10.1](https://github.com/middag-io/middag-php-framework/compare/v0.10.0...v0.10.1) (2026-06-04)


### Bug Fixes

* **deps:** require the reviewed middag-io/ui ^0.9 ([41d077f](https://github.com/middag-io/middag-php-framework/commit/41d077f6b58016fc4e077aa18a72e4a4b36e48ce))
* **deps:** require the reviewed middag-io/ui ^0.9 ([c3f5554](https://github.com/middag-io/middag-php-framework/commit/c3f5554c5cb943f3ce47208653f9c8f641cbfbda))

## 0.10.0 (2026-06-04)

Initial public release.

`middag-io/framework` is the platform-agnostic DDD base for PHP applications:
write your domain against contracts and run it standalone, inside Moodle, or
inside WordPress by swapping the adapter. It wraps Symfony, PSR, Monolog and
Inertia under one coherent API.

See [`docs/architecture.md`](docs/architecture.md) for the technical reference.
