# Member 3: Review types and their application to DearYou

Slides 15-21. Target: approximately 8-9 minutes including slide changes. The spoken script contains 1,019 words. References and activity notes are not intended to be read aloud. Replace the speaker names before rehearsing.

## Slide 15 - Four review types: choose the method for the purpose

**Suggested visual:** Four cards: informal review, walkthrough, technical review, inspection.

**Spoken script:**

“My part compares four types of review: informal review, walkthrough, technical review, and inspection. They all examine a work product without executing the website. The difference is how the people organize the examination and what they want to achieve.

For DearYou, we can review a requirement, a controller, an email policy, or a test plan. Opening the website and submitting feedback would be dynamic testing. Reading the feedback requirement and code to identify an inconsistency would be static testing.

There is no single best review type for every situation. We choose according to risk, complexity, available time, and the evidence we need. A small wording change may need a quick peer check. A change that could send hundreds of emails deserves more preparation and stronger follow-up. The four types give us different ways to make that choice.”

**Source:** Spillner, Linz, and Schaefer, fourth edition, printed pp. 79-80 and 88-94; PDF pp. 94-95 and 103-109.

## Slide 16 - Informal review: a quick peer check

**Suggested visual:** Author sends a small change to a colleague; colleague returns two concise comments.

**Spoken script:**

“An informal review is a lightweight examination by one or more colleagues. It has little prescribed procedure. There might be no meeting, and a short list of comments may be enough. However, informal does not mean careless. The reviewer still needs a clear question and enough context.

Imagine we change DearYou’s feedback setting from a technical label to a clearer sentence. We ask a teammate to read the wording and the associated requirement. The teammate notices that ‘five emails a day’ does not explain whether the limit applies to each person or the entire website. That ambiguity can be corrected before users see it.

This method is useful for small, low-risk changes and rapid feedback. Its limitation is that coverage depends heavily on who reviews it and what they notice. If we need recorded approval, several specialist viewpoints, or strict acceptance criteria, we should choose a more structured method.”

**Source:** Printed pp. 92-94; PDF pp. 107-109.

## Slide 17 - Walkthrough: the author explains the work

**Suggested visual:** Author guides teammates through a requirement-to-code diagram.

**Spoken script:**

“In a walkthrough, the author guides the other participants through the work product. The main goals include shared understanding, learning, and finding defects. Preparation can vary, and walkthroughs can be adapted to the team.

For example, the author of DearYou’s feedback email policy explains a scenario step by step: a visitor submits feedback, the application stores the record, and the notification policy decides whether an email may be sent. The author shows the requirement and relevant code while teammates ask questions.

One teammate asks, ‘What happens after the fifth permitted notification?’ Another asks, ‘Does blocking an alert also delete the feedback?’ These questions can reveal unclear requirements or missed cases without sending a real message.

A walkthrough is especially helpful when teammates need to understand unfamiliar code. However, the author’s explanation can guide everyone toward the same assumptions. Reviewers should still question the logic and examine the material themselves.”

**Source:** Printed pp. 89-90 and 102; PDF pp. 104-105 and 117.

## Slide 18 - Technical review: evaluate the technical decision

**Suggested visual:** Requirements, design options, and decision record feeding a technical discussion.

**Spoken script:**

“A technical review focuses on whether a work product is suitable for its purpose and consistent with specifications and standards. Participants need relevant technical knowledge. They prepare comments, compare alternatives, and discuss technical issues. The amount of formality can vary.

For DearYou, a suitable technical-review question is: ‘Will our feedback notification policy enforce one shared daily limit when several workers process jobs at the same time?’ Reviewers read the policy and supporting documentation. They consider how counting, reservations, retries, and date boundaries interact.

The important output is an evidence-based assessment and, where needed, a documented technical decision. A plain read-then-increment counter and an atomic reservation may behave differently under concurrency; the review should explain why the selected design meets the requirement.

This is still static testing because we examine the design and code. Running concurrent requests to measure the result would be a separate dynamic test.”

**Source:** Printed pp. 91-92 and 103; PDF pp. 106-107 and 118.

## Slide 19 - Inspection: a formal, recorded examination

**Suggested visual:** Entry criteria -> individual checklist review -> findings meeting -> rework -> exit criteria.

**Spoken script:**

“An inspection is the most formal of these four review types. It uses defined roles, individual preparation, checklists, recorded findings, and entry and exit criteria. A trained moderator guides the process. The author answers questions and later performs the required rework.

For a high-risk DearYou change, the entry criteria could require a stable code version, an agreed notification requirement, and successful syntax checks. Reviewers then inspect selected files using a checklist. They record findings with evidence rather than vague opinions.

The meeting consolidates the findings and resolves whether the work product meets the review criteria. It should not become an unlimited coding session. Corrections happen during rework, followed by verification that the findings were addressed.

The advantage is traceability and consistent coverage. The cost is more preparation and coordination. This is appropriate when the consequences of a mistake justify that effort, rather than for every tiny edit.”

**Source:** Printed pp. 90-91 and 102-103; PDF pp. 105-106 and 117-118.

## Slide 20 - Compare and select

**Suggested visual:** Comparison table with purpose, leader, preparation, and output.

**Spoken script:**

“We can summarize the choices using four questions. What is the purpose? Who guides the review? How much preparation is needed? What evidence must remain afterward?

An informal review gives quick peer feedback with minimal procedure. A walkthrough is author-led and emphasizes understanding and learning. A technical review uses qualified peers to assess technical suitability and discuss decisions. An inspection follows a defined process and produces traceable findings and follow-up evidence.

These categories are useful guides, not rigid boxes. A walkthrough may include advance reading. A technical review may be relatively informal. The textbook explains that organizations tailor reviews to their projects.

For DearYou, our proposed approach is a quick peer review for small text changes, a walkthrough for explaining a feature, a technical review for architectural choices, and an inspection for changes with significant email, security, or data-handling consequences.”

**Source:** Printed pp. 89-94; PDF pp. 104-109. The project selection examples are recommendations, not a claim that formal inspections have already occurred.

## Slide 21 - A worked review example and handover

**Suggested visual:** Clearly label the example “Illustrative inspection exercise.” Show requirement, simplified code, finding, and correction criterion.

**Spoken script:**

“Here is a short illustrative inspection exercise, not a claim about a current DearYou defect. Our requirement says that feedback notification emails must be limited to five across the website per UTC day. Our simplified code uses a separate counter for every sender.

The reviewer compares the code with the requirement and identifies a scope mismatch: ten senders could each receive a separate allowance. The scribe records the finding, its location, the expected shared limit, and the possible impact. The author then revises the design, and follow-up checks the correction against the requirement.

Notice that we found the mismatch by reading. We did not need to send fifty emails. A later dynamic test can confirm the actual runtime behavior.

This example also shows why roles matter. One person creates the work, another guides the review, reviewers identify issues, and the scribe preserves the evidence. Our next speaker will explain those responsibilities.”

**Source:** Review process and roles, printed pp. 82-87; PDF pp. 97-102. The requirement, code, and finding below form a fictional teaching example.

### Defined example artifact for Slide 21

Do not display account credentials, provider keys, inbox addresses, or actual visitor feedback.

**Review basis:** “At most five feedback notification emails may be sent across the whole website per UTC calendar day. Feedback records must still be stored.”

**Simplified teaching pseudocode, deliberately incorrect:**

```text
counter_key = sender_id + utc_date
if counter(counter_key) < 5:
    reserve_one_notification(counter_key)
    queue_feedback_notification()
```

| Field | Example record |
|---|---|
| Finding ID | DEMO-REVIEW-01 |
| Work product | Teaching pseudocode, line 1 |
| Observation | The allowance is separated by sender. |
| Expected behavior | One allowance is shared across the website for each UTC day. |
| Potential impact | Multiple senders can collectively exceed five notification emails. |
| Proposed severity | Major for this exercise: it defeats the stated cost-control requirement. |
| Rework owner | Author |
| Follow-up criterion | The revised design uses the intended shared scope and preserves stored feedback; concurrency and retry handling receive their own checks. |

Changing the key alone is not a complete concurrency-safe implementation. This exercise isolates the scope issue; the presentation must not imply that an ordinary counter is sufficient for production.

## Likely questions and concise answers

**Is a walkthrough the same as demonstrating the running website?**

No. For this topic, a walkthrough is an author-led review of a work product such as requirements or code. A running website demonstration can support the presentation, but exercising its behavior is dynamic testing.

**Is an inspection always better than an informal review?**

No. An inspection offers stronger structure and traceability but requires more effort. Choose according to risk, objectives, expertise, and the evidence required.

**Were these four review types formally performed on DearYou?**

The project has real code reviews and automated checks, but the four examples here explain how a team could apply each technique. We should call an activity a formal inspection only if we actually followed and documented the inspection process.

## Reference for the presentation bibliography

Spillner, A., Linz, T., & Schaefer, H. (2014). *Software Testing Foundations: A Study Guide for the Certified Tester Exam* (4th ed.). Rocky Nook. Chapter 4, especially pp. 79-94. ISBN 978-1-937538-42-2.
