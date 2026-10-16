# Project Software Testing

Prepared: 15 October 2026

## Purpose and current scope

Prepare a five-member group presentation on **Chapter 5: Static Testing Techniques**, using **DearYou** as the practical case study. The lecturer's topic corresponds to Chapter 4, **Static Test**, in the supplied study guide.

The presentation will explain the theory and demonstrate how reviewers can examine a real website's requirements, source code, and supporting documents without executing the application under review.

- Case-study website: https://dearyous.app
- Source repository: https://github.com/bundavit/Dearyou
- Planned core presentation: **50 minutes, 10 minutes per member**.
- Optional questions: reserve a further five minutes if the lecturer permits.
- Requested eventual deliverables: slides and speaker notes for the whole group.
- **Current work is limited to this plan and organizing the textbook. Slide creation remains paused.**

DearYou already provides enough material. This project does not require new website features or a production deployment.

## Textbook and supporting sources

Primary course reference: [Software Testing Foundations: A Study Guide for the Certified Tester Exam](Software_Testing_Foundations_A_Study_Guide_for_the_Certified_Tester.PDF), Andreas Spillner, Tilo Linz, and Hans Schaefer, fourth edition, 2014.

The page numbers below distinguish the book's printed pages from the PDF viewer's page count.

| Topic | Printed pages | PDF pages |
| --- | --- | --- |
| Static testing foundations and benefits | 79-82 | 94-97 |
| Generic review process | 82-86 | 97-101 |
| Review roles | 86-87 | 101-102 |
| Review types | 89-94 | 104-109 |
| Success factors | 93-95 | 108-110 |
| Automated static analysis | 95-97 | 110-112 |
| Data-flow analysis | 98-99 | 113-114 |
| Control flow and cyclomatic complexity | 99-102 | 114-117 |

Technical references for the tool demonstration:

- [PHP CLI options](https://www.php.net/manual/en/features.commandline.options.php): `php -l` checks syntax without executing the submitted PHP application code.
- [Laravel Pint](https://laravel.com/docs/pint): `--test` checks coding style without applying fixes.
- [PHPStan documentation](https://phpstan.org/user-guide/getting-started): possible further static analysis, not a tool already demonstrated in this project.

Use the lecturer's six-phase review terminology throughout. Other syllabus editions can group the activities differently.

## Why DearYou fits this chapter

| Required topic | Available DearYou material | Planned classroom use |
| --- | --- | --- |
| Foundations, benefits, and economics | Requirements for feedback status and notification limits | Explain how early review can prevent implementation mistakes and later rework |
| Generic review process | A small controller change and its review evidence | Follow planning, kick-off, preparation, meeting, rework, and follow-up |
| Informal review, walkthrough, technical review, inspection | The same feedback or response-validation change | Compare how each review type would examine the work product |
| Review roles and responsibilities | A proposed group inspection exercise | Assign author, moderator, reviewers, and scribe responsibilities |
| Automated static analysis | PHP source files and the installed Pint tool | Demonstrate syntax and style checks, then explain their limits |
| Data and control flow | Upload processing and a small conditional method | Trace data transformations and count independent control-flow paths |

The live website provides context. The principal evidence for this chapter comes from readable work products: requirements, code, review comments, checklists, and defect records.

## Five-member allocation and timing

Member numbers are planning labels. Replace them with the group's actual names when assigned.

| Presenter | Responsibility | Slide range | Time |
| --- | --- | --- | --- |
| Member 1 | Foundations, benefits, and economics of static testing | 1-7 | 00:00-10:00 |
| Member 2 | Generic review process and key success factors | 8-14 | 10:00-20:00 |
| Member 3 | Review types and their comparison | 15-21 | 20:00-30:00 |
| Member 4 | Review roles, a worked finding, and a staged inspection | 22-28 | 30:00-40:00 |
| Member 5 | Static tools, data flow, control flow, complexity, and conclusion | 29-35 | 40:00-50:00 |

Each ten-minute allocation includes the member's explanation, example or demonstration, and handover. The short inspection exercise in Member 4's section can involve all five members; the host keeps that exercise within its allocated time.

## Proposed slide outline

The 35-slide outline is provisional. Adjust slide count after rehearsal while preserving the agreed coverage and balanced speaking time.

### Member 1: Foundations, benefits, and economics

1. Presentation title, team, and learning objectives.
2. Meaning of static testing and the work products it examines.
3. DearYou overview: private letters, recipient responses, and administration.
4. Static and dynamic testing, with one example of each from DearYou.
5. Benefits: early defect detection, shared understanding, and maintainability.
6. Economics: review effort, avoided rework, and the limits of cost claims.
7. A reviewable DearYou requirement and handover to the review process.

Use a hypothetical repair-cost example only if it is clearly labeled hypothetical. Do not invent measured savings or universal cost multipliers.

### Member 2: Review process and success factors

8. The six-phase review process and its expected outputs.
9. Planning: scope, objectives, participants, and entry/exit criteria.
10. Kick-off: explain the work product and distribute the checklist.
11. Individual preparation: inspect independently and record questions.
12. Review meeting: discuss anomalies and consolidate findings.
13. Rework and follow-up: correct accepted defects and check closure.
14. Success factors and a proposed DearYou review plan.

The worked scope should be small, such as feedback status restoration. Avoid attempting to inspect the entire website in one meeting.

### Member 3: Types of reviews

15. Overview of informal review, walkthrough, technical review, and inspection.
16. Informal review: purpose, process, and a lightweight DearYou example.
17. Walkthrough: the author explains a scenario through the work product.
18. Technical review: qualified peers examine rules, design, and alternatives.
19. Inspection: defined roles, preparation, checklists, records, and follow-up.
20. Comparison table: objectives, leadership, preparation, formality, and outputs.
21. Choosing a review type for a particular DearYou change.

Explain that review practices vary. A walkthrough can include preparation, and technical reviews can use different levels of formality.

### Member 4: Roles and responsibilities

22. Roles in a review and why responsibilities must be explicit.
23. Author, review leader, moderator, and management support.
24. Reviewers/inspectors, scribe, and a focused checklist.
25. Retrospective walkthrough of DearYou's feedback status defect.
26. Proposed five-person inspection demonstration.
27. A reconstructed defect record with evidence and an action owner.
28. Rework, follow-up, and handover to automated analysis.

The five presentation members and the review roles are separate concepts. A person can have different responsibilities in different review activities. Label the role-play as a classroom demonstration.

### Member 5: Tools, flow analysis, and complexity

29. Manual reviews and tool-supported static analysis.
30. DearYou syntax/style demonstration and the recorded results.
31. Data-flow notation: `d` defined, `r` referenced, `u` undefined.
32. Illustrative `ur`, `du`, and `dd` anomalies and their interpretation.
33. Control-flow graph of a simplified DearYou method.
34. Worked cyclomatic complexity calculation and its limitations.
35. Summary, evidence boundaries, references, and transition to questions.

Keep source details and fuller reference entries in speaker notes or optional appendix slides if the closing slide becomes crowded.

## Selected case-study evidence

### A. Feedback restored to New became Reviewed again

- Work product: `backend/app/Http/Controllers/Admin/FeedbackController.php`.
- Historical revision: `e64a15d`.
- Corrected revision: `8f1c618`.
- Review reasoning: the old update method saved New, then redirected back to the detail page. Opening the detail page called `show()`, which changed New to Reviewed.
- Correction: restored New feedback redirects to the filtered list instead of reopening its detail page.
- Review question: **Does the redirect destination undo the intended state change?**

This is the main walkthrough and inspection example because the relationship between two short methods is easy to explain.

### B. A valid string can still be an invalid response choice

- Work product: `backend/app/Http/Controllers/PublicLetterController.php`.
- Historical validation accepted a required string with a length limit.
- Corrected validation restricts values to the choices allowed by the selected response mode.
- Review question: **Do the server's rules match the permitted business choices?**

This example supports a technical review of requirements and server validation.

### C. Upload data needs transformation before database storage

- Work products: the API letter controller, `LetterRequest.php`, and `backend/app/Support/LetterData.php`.
- Trace: validated upload input, file storage, normalized attributes, database path column.
- Review question: **Does each input become the correct stored value before persistence?**

This is an optional supporting example. Keep the main talk focused on A and B if rehearsals run long.

**Evidence boundary:** these historical defects were originally discovered during live, dynamic QA. The presentation uses retrospective static walkthroughs to explain them. Do not claim a static checker discovered them or that the group already conducted a formal inspection.

## Tool demonstration plan

Use a local VS Code terminal. Run checks against the selected source files, without sending emails or making changes on the production server.

```powershell
Set-Location D:\Dearyou\backend
php -l app/Http/Controllers/Admin/FeedbackController.php
php -l app/Http/Controllers/PublicLetterController.php
php -l app/Support/LetterData.php
php vendor/bin/pint --test app/Http/Controllers/Admin/FeedbackController.php app/Http/Controllers/PublicLetterController.php app/Support/LetterData.php
```

These checks were already run during preparation: the three PHP syntax checks passed and Pint passed for those three files. Save a fresh screenshot or transcript for the final presentation if the source changes before class.

Explain that syntax/style success does not establish correct behavior, security, successful email delivery, or successful type analysis. PHPStan has not been demonstrated here. PHPUnit execution, browser interaction, API requests, and email delivery checks are dynamic testing.

## Flow-analysis examples

Use the textbook's notation consistently:

| Sequence | Meaning | Teaching example |
| --- | --- | --- |
| `ur` | Undefined, then referenced | Read a variable along a path where it was never initialized |
| `du` | Defined, then undefined without a reference | Assign a value, then leave its scope without reading it |
| `dd` | Defined, then redefined before a reference | Assign a limit, then overwrite it before using the first value |

Label these as teaching examples unless an actual occurrence is verified in DearYou. An anomaly warrants investigation and is not automatically a functional defect. In this notation, `u` means undefined, not use.

For complexity, use a simplified graph of `FeedbackController::show()`:

- Nodes: Entry, New-status decision, Update, Return view, Exit. **N = 5**.
- Edges: Entry to decision; true branch to Update; false branch to Return; Update to Return; Return to Exit. **E = 5**.
- One connected flow graph: **P = 1**.
- **V(G) = E - N + 2P = 5 - 5 + 2 = 2**.
- Explain the two independent paths: New feedback and feedback already in another status.

This is a manual calculation for a simplified method, not a measured complexity score for the whole application. Cyclomatic complexity counts independent paths. It is not a bug count, a count of every possible execution path, or an exact branch-coverage test requirement.

## Preparation sequence

| Step | Work | Completion evidence |
| --- | --- | --- |
| 1 | Confirm member names, lecturer expectations, and question time | Named responsibility list and agreed timing |
| 2 | Read the assigned textbook pages and prepare personal summaries | Each member can explain their concepts without reading slides |
| 3 | Select the two main DearYou examples and freeze cited revisions | Short, redacted before/after snippets with file references |
| 4 | Prepare the review plan, checklist, and reconstructed finding | Clearly labeled classroom artifacts |
| 5 | Create slides and speaker notes after work resumes | Complete draft covering all five sections |
| 6 | Check technical accuracy, readability, citations, and handovers | Peer review comments resolved |
| 7 | Rehearse and shorten or expand explanations | A timed 50-minute core presentation with balanced participation |

Keep account credentials, API keys, verification codes, recipient addresses, private letters, and real feedback content out of presentation evidence. Use short code excerpts and synthetic or public examples. Carry offline copies of the slides, notes, and demonstration output.

## Rubric alignment

| Criterion | Marks | Planned evidence |
| --- | --- | --- |
| Group: Content quality | 3 | Textbook coverage, accurate definitions, cited DearYou examples |
| Group: Organization and flow | 2 | Five coherent sections, clear objectives, rehearsed handovers |
| Group: Visuals and aids | 2 | Readable code excerpts, review comparison table, process/flow visuals |
| Group: Team coordination | 3 | Ten minutes per member, shared review exercise, timed rehearsal |
| Individual: Delivery and confidence | 3 | Clear audio, understandable pace, practice explaining examples |
| Individual: Subject mastery | 3 | Explain the assigned concepts and justify the case-study reasoning |
| Individual: Engagement | 2 | Prepare to handle questions about the assigned section |
| Individual: Time management | 2 | Stay within the allocated ten minutes with balanced pacing |

The lecturer waived eye contact, body language, and audience interaction for the online class. Question handling still matters. This plan supports the rubric and does not guarantee a particular score.

## Questions to prepare

1. Why is running a static-analysis tool still static testing?
2. Can static testing review requirements before executable software exists?
3. How do walkthroughs, technical reviews, and inspections differ?
4. What makes a recorded anomaly useful to the author?
5. Why did syntax/style checks pass even though DearYou previously had behavior bugs?
6. Does `du` always mean the application will fail?
7. What does a cyclomatic complexity of two tell us, and what does it not tell us?
8. Which project claims are historical observations, reconstructed examples, or proposed classroom activities?

## Completion checklist

- [x] Confirm DearYou is suitable for the assigned topics.
- [x] Create this project folder and save the plan.
- [x] Move the supplied textbook into this folder and verify its contents are unchanged.
- [ ] Assign actual member names.
- [ ] Agree the final example snippets and classroom review artifacts.
- [ ] Resume slide and speaker-note creation when requested.
- [ ] Review the complete draft and prepare answers.
- [ ] Complete a timed group rehearsal and keep offline presentation copies.

No presentation date has been assumed. No application code, Git commits, or production settings are changed by this planning work.
