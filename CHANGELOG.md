# Changelog

## [1.13.0](https://github.com/middag-io/middag-php-framework/compare/v1.12.1...v1.13.0) (2026-07-29)


### ⚠ BREAKING CHANGES

* **shared:** tira o caso Raw do enum Operator

### Features

* **shared:** tira o caso Raw do enum Operator ([b5f3179](https://github.com/middag-io/middag-php-framework/commit/b5f317982cbe26b5c5a06b462276bee4375d77a5))

## [1.12.1](https://github.com/middag-io/middag-php-framework/compare/v1.12.0...v1.12.1) (2026-07-27)


### Bug Fixes

* **http:** não responder visita Inertia com JSON puro ([c02dec2](https://github.com/middag-io/middag-php-framework/commit/c02dec28260bde75f7270e33fc021de2c0012aae))
* **http:** não responder visita Inertia com JSON puro ([c0d86e1](https://github.com/middag-io/middag-php-framework/commit/c0d86e1335366a9c4a77a657b0b37a22ff484b76))
* **http:** não responder visita Inertia com JSON puro ([#72](https://github.com/middag-io/middag-php-framework/issues/72)) ([c02dec2](https://github.com/middag-io/middag-php-framework/commit/c02dec28260bde75f7270e33fc021de2c0012aae))

## [1.12.0](https://github.com/middag-io/middag-php-framework/compare/v1.11.0...v1.12.0) (2026-07-25)


### Features

* **http:** execute route-default middleware on both handler paths ([9bb1f58](https://github.com/middag-io/middag-php-framework/commit/9bb1f5862f2f90c1fabae9cd314a1c8253a58d8b)), closes [#68](https://github.com/middag-io/middag-php-framework/issues/68)
* **shared:** add Uuid value object with RFC 4122 parse and v4/v7 minting ([79ca257](https://github.com/middag-io/middag-php-framework/commit/79ca25725cb65aa84fd0e85d904aa50091fbbdb6)), closes [#69](https://github.com/middag-io/middag-php-framework/issues/69)

## [1.11.0](https://github.com/middag-io/middag-php-framework/compare/v1.10.0...v1.11.0) (2026-07-24)


### Features

* **http:** segregate ControllerInterface into role interfaces (ISP) ([04eebe3](https://github.com/middag-io/middag-php-framework/commit/04eebe3504c322a7ca35cd46ff7de936d5d07549))
* **http:** segregate ControllerInterface into role interfaces (ISP) ([d50c66d](https://github.com/middag-io/middag-php-framework/commit/d50c66d4d2fee11a8cbd328613fb8f764e75cdc9))

## [1.10.0](https://github.com/middag-io/middag-php-framework/compare/v1.9.0...v1.10.0) (2026-07-24)


### Features

* FW batch — portability gate, route auto-discovery, custom casts, webhook primitives ([028520e](https://github.com/middag-io/middag-php-framework/commit/028520ed5dde1ecb14d34dcc234ba91ef650c49b))
* **http:** non-blocking RetryPolicy with Retry-After support ([dfdb8df](https://github.com/middag-io/middag-php-framework/commit/dfdb8df84b75b72ce83b755cdd763d35c34ddbcd))
* **http:** route auto-discovery bridge (opt-in, multi-dir) ([8206d0d](https://github.com/middag-io/middag-php-framework/commit/8206d0d72bde6a183bed1379c31c839ccd4ef7bd))
* **persistence:** custom casts via CastInterface in $casts ([f1b1c2e](https://github.com/middag-io/middag-php-framework/commit/f1b1c2e71a4472c998ecf12e4adbd801b48728e5))
* **portability:** wire deptrac + host-globals scan for FW-011 ([fb3a1c9](https://github.com/middag-io/middag-php-framework/commit/fb3a1c98970472422946a1ace69da0184197c071))
* **security:** HMAC webhook signature verifier ([b3b900a](https://github.com/middag-io/middag-php-framework/commit/b3b900a36c567cce8845cb71643e99e30d6a2e08))


### Documentation

* **events:** specify public hook surface and ratify FW-007 ([a63e486](https://github.com/middag-io/middag-php-framework/commit/a63e486bac9eb64300a56cc5a3dd2b8f483c5635))

## [1.9.0](https://github.com/middag-io/middag-php-framework/compare/v1.8.0...v1.9.0) (2026-07-24)


### ⚠ BREAKING CHANGES

* **database:** SchemaBuilderAdapterInterface::dropIndex() gains a third parameter ($fields); external implementations of the interface must add it to stay signature-compatible.

### Features

* **database:** resolve xmldb index drop by field-set ([d38ddce](https://github.com/middag-io/middag-php-framework/commit/d38ddce8635b3e5deefb34bbe5c18bd51d488c96))


### Documentation

* reformat FW-001..013 to MADR-compact, extract REF to docs-middag-dev ([b0b59c0](https://github.com/middag-io/middag-php-framework/commit/b0b59c00531ab5f762fd7b1af885ecb34ea2146e))

## [1.8.0](https://github.com/middag-io/middag-php-framework/compare/v1.7.2...v1.8.0) (2026-07-16)


### Features

* **database:** add MySQL dialect and host-agnostic query/transaction seams ([0c0b96b](https://github.com/middag-io/middag-php-framework/commit/0c0b96b1ece9441e13069e28d23652bf75c15ebc))
* **shared:** add Typing::toNumber, Inflector and Arr::mergePreferNonNull utils ([7e7cf3e](https://github.com/middag-io/middag-php-framework/commit/7e7cf3ec5b12fbaa06b20af3e14b71136aed21fb))

## [1.7.2](https://github.com/middag-io/middag-php-framework/compare/v1.7.1...v1.7.2) (2026-07-16)


### Miscellaneous

* release 1.7.2 ([6f31c1d](https://github.com/middag-io/middag-php-framework/commit/6f31c1dc11a12bd8b29303721dff6c8fbe53a063))
* sync develop into main for 1.7.2 ([835ae05](https://github.com/middag-io/middag-php-framework/commit/835ae05e16b5cb8d8d7c2619879785c69ed00562))

## [1.7.1](https://github.com/middag-io/middag-php-framework/compare/v1.7.0...v1.7.1) (2026-07-13)


### Documentation

* **kernel:** multi-host warning on HostContext (O5-FW-01) ([48974e2](https://github.com/middag-io/middag-php-framework/commit/48974e2f6355d841f04ca5c6969e0a76c1930f18))


### Miscellaneous

* merge develop (CI PSR-4 guards) into refactor/wp-lib-v2 ([9c81101](https://github.com/middag-io/middag-php-framework/commit/9c811017a36ed3d8b28abc1c8f3a455dd830846d))

## [1.7.0](https://github.com/middag-io/middag-php-framework/compare/v1.6.0...v1.7.0) (2026-07-09)


### Features

* **logging:** add ErrorLogFallbackLogger zero-dep PSR-3 fallback ([b4d9e83](https://github.com/middag-io/middag-php-framework/commit/b4d9e831be9a898143dc1c95d33d85a6e736f6c7))


### Miscellaneous

* promote develop for 1.7.0 release ([ab2fbb3](https://github.com/middag-io/middag-php-framework/commit/ab2fbb30fcd33c1cd25c7606f9dc271a5340b37e))

## [1.6.0](https://github.com/middag-io/middag-php-framework/compare/v1.5.1...v1.6.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* enum case names changed for Capability, DebugMode, Operator and LogLevel. Consumers referencing cases by name must update (backed values unchanged). Requires middag-io/ui with PascalCase cases.
* **http:** remove legacyCapability() from CapabilityRequirement

### Features

* rename all enum cases to strict PascalCase (PER-CS 2.0) ([1d58660](https://github.com/middag-io/middag-php-framework/commit/1d586604e5bd4eefd4347cc971562e6016bfcf4f))


### Refactoring

* **http:** remove legacyCapability() from CapabilityRequirement ([91160f4](https://github.com/middag-io/middag-php-framework/commit/91160f49ed13cd5fefa2b6bb7ba02722d9b2cf21))


### Miscellaneous

* **deps:** drop v prefix from ui constraint ([3114a5b](https://github.com/middag-io/middag-php-framework/commit/3114a5b62af6666c8c15b9762a5f2e653688c58b))
* **deps:** require middag-io/ui ^1.3 ([a451414](https://github.com/middag-io/middag-php-framework/commit/a4514140a45da94f73154ee17ef13bf4642c7cc7))
* release 1.6.0 ([d899e9b](https://github.com/middag-io/middag-php-framework/commit/d899e9b399c1e8aed73eca5f6468b3a8c3bb3b0c))
* release framework 1.6.0 ([090782b](https://github.com/middag-io/middag-php-framework/commit/090782bba628379a1951af08054bd01fffcb35c7))

## [1.5.1](https://github.com/middag-io/middag-php-framework/compare/v1.5.0...v1.5.1) (2026-07-08)


### Miscellaneous

* release 1.5.1 ([fd7836f](https://github.com/middag-io/middag-php-framework/commit/fd7836fa2076fe1c08bd5e1841f41c3132399a2c))

## [1.5.0](https://github.com/middag-io/middag-php-framework/compare/v1.4.0...v1.5.0) (2026-07-08)


### Features

* **persistence:** add relation references ([0e4b705](https://github.com/middag-io/middag-php-framework/commit/0e4b705))

## [1.4.0](https://github.com/middag-io/middag-php-framework/compare/v1.3.0...v1.4.0) (2026-07-08)


### Features

* **database:** typed schema descriptor attributes + reflection reader ([167ff16](https://github.com/middag-io/middag-php-framework/commit/167ff16f3f7263263010769f4d7e58c67d995614))
* **database:** typed schema descriptor attributes + reflection reader ([8b211ff](https://github.com/middag-io/middag-php-framework/commit/8b211ffd0711dbce13beb83ea11ad8beccf64771))

## [1.3.0](https://github.com/middag-io/middag-php-framework/compare/v1.2.1...v1.3.0) (2026-07-07)


### ⚠ BREAKING CHANGES

* **exception:** MiddagLifecycleViolationException is no longer @api.

### Features

* **http:** add PSR-18 HttpClientFactory ([@api](https://github.com/api)) [D-12/LB-2-01] ([3d07074](https://github.com/middag-io/middag-php-framework/commit/3d070740d077abfdefd362e948c13c749d8c0917))
* **http:** forward rich #[Auth] requirements to opt-in controllers [LB-2-04] ([23c0aba](https://github.com/middag-io/middag-php-framework/commit/23c0aba46bd013fd1578d3f3f207745427feb187))
* **http:** rich capability requirements for #[Auth] [N-03/LB-2-03] ([9dbcf9d](https://github.com/middag-io/middag-php-framework/commit/9dbcf9d4b3b15b34565c1e6afe55c43bcbf1d582))


### Refactoring

* **exception:** MiddagLifecycleViolationException [@api](https://github.com/api) -&gt; [@internal](https://github.com/internal) [D-17b/LB-3-03] ([f42339e](https://github.com/middag-io/middag-php-framework/commit/f42339e7e69f129325091095f06e8fe25e8c1355))


### Documentation

* framework CLAUDE.md — drop SortDirection, add DDD/util split note [LB-0-14/LB-0-06] ([84223d4](https://github.com/middag-io/middag-php-framework/commit/84223d4d637fa0e1adbf411b53e3e6140f56a672))


### Miscellaneous

* **dist:** export-ignore /bin in the published archive ([3a78725](https://github.com/middag-io/middag-php-framework/commit/3a78725fa8247f6a886caeffda0d1af74e7ee654))
* release 1.3.0 ([ccaa731](https://github.com/middag-io/middag-php-framework/commit/ccaa7312c9cface31e246c46a999f305885b8514))

## [1.2.1](https://github.com/middag-io/middag-php-framework/compare/v1.2.0...v1.2.1) (2026-07-07)


### Miscellaneous

* **ci:** document the real reusable-workflow limitation ([1410fdc](https://github.com/middag-io/middag-php-framework/commit/1410fdc0d5458b63136665ce682242855f78ded0))
* release 1.2.1 ([531127b](https://github.com/middag-io/middag-php-framework/commit/531127b07c636bc335e050988ff66f54215215af))
* release 1.2.1 ([adc072f](https://github.com/middag-io/middag-php-framework/commit/adc072f58f0c7c30c3198eafa72cb6450dd1c355))
* widen PSR constraints and pin ui to ^v1.2.3 ([246782c](https://github.com/middag-io/middag-php-framework/commit/246782c983fa6f5c4ead74bf73c97c5c7dd9e935))

## [1.2.0](https://github.com/middag-io/middag-php-framework/compare/v1.1.2...v1.2.0) (2026-07-06)


### ⚠ BREAKING CHANGES

* **persistence:** MapperInterface::dbToDomain() now takes array $record (was stdClass) and domainToDb() now returns array (was stdClass). Implementors must update their signatures.

### Features

* **kernel:** add generic FailedModuleRegistryInterface contract ([d3a25a2](https://github.com/middag-io/middag-php-framework/commit/d3a25a2aff1542fa0b3b085b890887bceb31cc73))
* **persistence:** array-native record contract in MapperInterface ([997ba8a](https://github.com/middag-io/middag-php-framework/commit/997ba8a3575b6ee6dbf351ea831bacc74744b21c))


### Bug Fixes

* **schema:** qualify DBAL index names with the table for global uniqueness ([7c26043](https://github.com/middag-io/middag-php-framework/commit/7c2604302617d13f94900ec6118bedbb461331a6))
* **schema:** qualify SQLite index names with the table for global uniqueness ([0149edf](https://github.com/middag-io/middag-php-framework/commit/0149edf0bb3f42f614a5aacf21eef704091f4850))


### Miscellaneous

* release framework 1.2.0 ([c5a1c30](https://github.com/middag-io/middag-php-framework/commit/c5a1c30188ec905cb6be6a643509d6f247c76758))

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
