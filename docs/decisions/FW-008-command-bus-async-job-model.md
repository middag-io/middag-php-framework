---
title: 'One command bus over Messenger; sync or async decided by routing'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [framework, bus]
deciders: [leadership]
decision: 'One Messenger bus where routing picks sync or async; OSS ships durable transport, retry and cron for standalone.'
related: [FW-007, FW-015, FW-017]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-008: One command bus over Messenger; sync or async decided by routing

## Context

Business work needs a first-class unit that can be tested and triggered outside a scheduler, and that runs either inline or later on a worker. A product running standalone, with no Moodle or WordPress cron and no proprietary packages, still needs a durable queue and recurring triggers.

## Considered Options

1. Business logic inside host cron and task adapters.
2. Separate dispatch methods for sync and async.
3. Generic PHP or JSON serialization of command payloads.
4. Only an in-memory transport in the OSS, with durable transport, retry and recurring triggers left to proprietary packages.
5. One bus over Symfony Messenger, where routing decides sync or async, with an explicit payload contract. The OSS ships a durable transport, retry and a cron engine for standalone hosts. Locking belongs to the host and job governance lives downstream. ← chosen

## Decision

Commands are the unit of explicit work, and a host's cron or task mechanism is only a thin trigger around them. There is one dispatch call. Whether a message runs inline or goes to a transport for a worker is decided by routing, not by the caller. Payloads cross the queue through the command's own `toPayload`/`fromPayload` contract. The OSS includes the standalone plumbing that holds no product knowledge: a durable database-backed transport, retry policies and a cron engine for recurring commands (decision A2). Running a worker or a schedule only once at a time is the host's job. Governed job records (attempts, correlation, deduplication, audit) are a concern of downstream proprietary packages.

## Consequences

- (+) Units of work can be tested and triggered without a scheduler.
- (+) A standalone product can run a worker with OSS code only.
- (−) The durable transport fits standalone hosts only. Inside Moodle or WordPress it opens a second connection and creates a table outside the host's schema tooling, and the host has to refuse it.
- (−) The OSS has no concurrency control. Overlapping workers or schedule runs are prevented by the host.
- (−) An incompatible change to a payload's shape needs coordinated rollout, because payloads are not versioned.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| One dispatch call; routing decides sync or async | test `tests/Bus/AsyncCommandBusTest.php` | coded |
| Explicit payload contract | test `tests/Bus/CommandSerializerTest.php` | coded |
| Handler resolution by convention or attribute | test `tests/Bus/AttributeHandlersLocatorTest.php` | coded |
| Durable standalone transport | test `tests/Bus/Transport/DoctrineTransportFactoryTest.php` | coded |
| Retry policy and cron engine | tests `tests/Bus/Retry/MultiplierRetryPolicyTest.php`, `tests/Bus/Schedule/ScheduleSupportTest.php` | coded |
| Scheduling syntax, worker limits, transport selection, anti-patterns | doc `docs-middag-dev:docs/framework/reference/command-bus.md` | coded |
