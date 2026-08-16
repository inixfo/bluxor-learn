# Shared Sub-Workflow Versioning Checklist

## Safe/additive change
- New optional input with a default.
- New output field while all existing fields remain unchanged.
- Internal node refactor with identical public output.

## Potentially breaking change
- Rename/remove an input.
- Change a field's type or meaning.
- Rename/remove an output field.
- Change from one item to many items (or the reverse).
- Change wait/fire-and-forget semantics.
- Add a new side effect.

## Recommended migration
1. Create `v2` as a separate child workflow for breaking contracts.
2. Keep `v1` published while callers migrate.
3. Update one parent at a time and run contract tests.
4. Observe failures/latency.
5. Mark v1 deprecated in the contract registry.
6. Remove v1 only after no callers remain.
