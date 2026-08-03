# Project coding rules

- Keep Vue components, TypeScript, templates, and styles readable. Do not write multiple imports, declarations, statements, template elements, or CSS rules on one line merely to make a file shorter.
- Use conventional multiline formatting for functions, objects, component props, emitted events, long attributes, and nested markup.
- Prefer small, named, reusable components over global selectors that alter unrelated UI components.
- Cover all new functionality and behavior changes with automated tests. Add regression tests for bug fixes, and verify relevant validation, authorization, filters, sorting, and edge cases rather than only checking that code or routes exist.
- When a UI filters by a date range, always reuse the shared `DateRangePicker` from `@/shared/ui`; do not build separate start/end date inputs or a custom range calendar.
- Implement backend list filters with a dedicated class extending `App\Core\Filters\Filter`, add `App\Core\Traits\HasFilter` to the filtered Eloquent model, and apply the filter through `->filter($filter)`. Do not add request-driven `where`, `when`, or conditional filter clauses directly in controllers, actions, or repositories. Pagination, sorting, authorization, and invariant domain constraints are not filters and should remain explicit.
