# SDD Workflow

## Overview

This document outlines the **Spec-Driven Development (SDD) workflow** for this project. The workflow is designed to be consistent, repeatable, and aligned with the framework conventions established in the steering documents.

## SDD Workflow Steps

### 1. Capture Idea (sdd-idea)
- Use `/skill:sdd-idea` to capture initial concepts.
- Define:
  - Feature name
  - Business value
  - Target users
  - Success criteria
- Mark status as `Open` or `In Progress`

### 2. Create Plan (sdd-plan)
- Use `/skill:sdd-plan` to create a roadmap.
- Define:
  - Feature ID (e.g., 001)
  - Scope boundaries
  - Dependencies
  - Estimated effort
- Mark status as `Planned` or `In Progress`

### 3. Define Requirements (sdd-prd)
- Use `/skill:sdd-prd` for detailed requirements.
- Include:
  - User stories
  - Acceptance criteria
  - Non-functional requirements
  - Edge cases
- Mark status as `Ready` or `Approved`

### 4. Design Implementation (sdd-spec)
- Use `/skill:sdd-spec` to create technical specifications.
- Include:
  - Component architecture
  - API endpoints
  - Data models
  - Edge cases
  - Technical risks
- Mark status as `Design Complete`

### 5. Break into Tasks (sdd-tasks)
- Use `/skill:sdd-tasks` to decompose the design into practical tasks.
- Each task should:
  - Have clear acceptance criteria
  - Be assigned to a file path
  - Include verification steps

### 6. Implement Code
- Follow framework conventions:
  - Use controllers for business logic
  - Use models for data access
  - Follow HTMX patterns
  - Implement pagination correctly
  - Use respect/validation for input validation

### 7. Write Tests
- Follow test standards:
  - Request body crudo
  - TearDown cleanup
  - Login injection for protected routes
  - Test success, validation errors, and non-existent IDs

### 8. Review (sdd-review)
- Use `/skill:sdd-review` to verify implementation.
- Check coverage against requirements and design.
- Ensure all acceptance criteria are met.

### 9. Handoff (sdd-brief)
- Create `.ai/sdd/handoff/sdd-brief.md` after approved review.
- Document:
  - Final implementation
  - Lessons learned
  - Open questions

## Workflow Diagram

```
Idea → Plan → Requirements → Design → Tasks → Implementation → Tests → Review → Handoff
```

## Open Questions

- Should we implement a feature template for common CRUD operations?
- Should we add a validation checklist for new features?
- How to handle dependencies between features in the plan?

---

*Workflow SDD – Generado durante la inicialización*