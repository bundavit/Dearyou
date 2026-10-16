# Member 4 speaker notes — Review roles and responsibilities

Allocated time: 10 minutes, slides 22–28. The spoken script is approximately 970 words. Timings include pointing to the code trace, short pauses, and a staged group demonstration. Replace member numbers with names before presenting.

Evidence boundary: the DearYou defect below was originally discovered through live, dynamic QA. This presentation reconstructs how a static review can explain it. The classroom inspection is a proposed demonstration, not a claim that the group previously conducted a formal inspection.

## Slide 22 — People make a review effective

Target: 1:00. Visual: five people around one work product; label responsibilities rather than job seniority.

**Spoken script**

“We have compared review types. I will now explain who does what, using one DearYou feedback feature as our example.

A review needs people with clear responsibilities. Otherwise, everyone may assume somebody else checked the important part. The author understands the implementation, reviewers bring independent questions, the moderator keeps the discussion productive, and the scribe preserves the findings.

In a formal review, a review leader also organizes the activity and its completion. Management provides resources and time. These are responsibilities, so their allocation depends on the review process; they do not always require separate people.

Our aim is to improve the work product. We discuss the behavior of the code, rather than judging the person who wrote it.”

## Slide 23 — Author, review leader, and moderator

Target: 1:20. Visual: a responsibility table with “prepare,” “organize,” and “facilitate.”

**Spoken script**

“The author makes the work product available, explains its purpose, answers questions, and corrects accepted defects. For DearYou, the author should explain what restoring feedback to New is supposed to do, and identify the relevant controller methods.

The review leader arranges the review: its scope, participants, schedule, and completion criteria. A useful scope here is the feedback status change and its redirect. Reviewing the entire website in one short meeting would be unrealistic.

The moderator facilitates the discussion. They keep us on the agreed scope, ensure quieter reviewers can contribute, and prevent the meeting from becoming a long debugging session.

For example, if somebody starts redesigning all email delivery while we are reviewing feedback status, the moderator records that separate concern and brings us back to the current question. Clear scope helps us use our limited time well.”

## Slide 24 — Reviewer, scribe, and checklist

Target: 1:20. Visual: a short checklist beside a sample finding.

**Spoken script**

“Reviewers examine the work product and identify possible anomalies. Different perspectives help: one reviewer may follow the user journey while another traces state changes and redirects.

The scribe records enough information for somebody to reproduce the reasoning later: the location, expected behavior, observed code path, and the decision or next action. A comment such as ‘feedback broken’ is too vague.

Our small checklist asks five questions. What behavior is required? Where does the state change? What happens immediately afterward? Do all branches preserve the requirement? What evidence will show that the correction is complete?

A checklist guides attention, but it cannot replace understanding. In this example, reading only the update method would miss the problem. The reviewer must also inspect the method reached by the redirect.”

## Slide 25 — DearYou: trace a feedback status change

Target: 1:40. Visual: two short code excerpts and the path “Restore New → detail page → Reviewed.” Show the historical revision separately from the current correction.

**Spoken script**

“Here is our retrospective DearYou example. During earlier live QA, restoring feedback to New did not leave it New. That discovery was dynamic testing. We are now using the source code to explain the defect without running the website.

In the earlier revision, the update method saved the requested status and returned back to the previous page. When the request came from the detail page, that redirect opened the detail page again.

Now read the show method. If the feedback status is New, opening that page changes it to Reviewed. Each method can look reasonable on its own. Together, they create an unwanted sequence: save New, redirect to detail, automatically save Reviewed.

The correction sends restored New feedback to the filtered list instead. The list does not immediately mark the item as reviewed. The important review technique is tracing the complete path across methods, including the destination of a redirect.”

## Slide 26 — A proposed five-person inspection demonstration

Target: 1:40, including the staged dialogue. Visual: role badges beside each speaker. Read the following as a planned classroom demonstration.

**Spoken script**

“We will demonstrate how those responsibilities work. This is a staged inspection of a historical defect, rather than a record of an inspection our group already completed.”

**Member 2, moderator:** “Our scope is restoring feedback to New. Please identify the requirement and the code path, without opening the live website.”

**Member 1, author:** “The intended result is that the restored item remains New until an administrator opens its detail page. The old update method saves the status and redirects back.”

**Member 3, reviewer:** “The detail page calls show. That method changes New feedback to Reviewed, so the redirect immediately reverses the intended result.”

**Member 4, second reviewer:** “The proposed fix redirects New items to the list. We should also check that other status changes still behave as intended.”

**Member 5, scribe:** “I will record the two methods, the unwanted transition, the proposed correction, and the checks needed before closure.”

**Member 2, moderator:** “We have a concrete finding. We will record it and leave implementation work for rework.”

## Slide 27 — Turn a finding into a useful defect record

Target: 1:30. Visual: the illustrative record below. Label it “Reconstructed classroom record.”

**Spoken script**

“A useful record makes the reasoning reviewable. Our example identifier is CLASS-R01. It is a teaching identifier, not an existing production ticket.

The title is ‘Restoring feedback to New immediately marks it Reviewed.’ The location includes both update and show in the admin FeedbackController. The expected result is New status after restoration; the code trace explains why the previous result became Reviewed.

We record a proposed impact assessment: administrators may overlook feedback they intended to revisit. The team should agree on severity using its own definitions, rather than assigning a dramatic label without context.

Finally, the record needs an owner and status. A possible decision is ‘accept for correction,’ followed by ‘ready for follow-up.’ The scribe captures the decision accurately; recording a finding does not, by itself, prove that a fix is complete.”

## Slide 28 — Rework, follow-up, and handover

Target: 1:30. Visual: “Finding → correction → review → evidence → closure.”

**Spoken script**

“During rework, the author implements the agreed correction. During follow-up, the responsible reviewer or review leader checks that the accepted finding has been addressed according to the review’s completion criteria.

For this case, static follow-up reads the new redirect branch and checks its interaction with show. A separate dynamic regression check can restore a synthetic feedback item and confirm that it stays New on the list. These are complementary activities, and we should label them correctly.

The actual DearYou correction was included in revision 8f1c618 and subsequently passed a live retest. That is historical project evidence; our role-play is only a teaching reconstruction.

My section shows how people, a checklist, and a clear defect record turn a code concern into an actionable improvement. Our final speaker will show how automated checks and flow analysis support this human review work.”

## Presenter reference notes — not spoken

- Historical before-state: commit `e64a15d`, `backend/app/Http/Controllers/Admin/FeedbackController.php`, `update()` ends with `return back()` after saving status.
- Current source: `backend/app/Http/Controllers/Admin/FeedbackController.php:44–50` (`show()`); `:53–77` (`update()`), especially the list redirect at `:71–74`.
- Deployed correction: `8f1c618b40a77d8358b252643cd35c54a49b34e6`.
- Portable release summary: [QA fix release](../../docs/QA_FIX_RELEASE.md) and [dated project status](../../docs/STATUS.md). Detailed raw QA evidence remains local and is not part of the Git release.
- Illustrative record fields for slide 27: ID `CLASS-R01`; historical revision `e64a15d`; methods `update()` and `show()`; expected status `new`; inferred path `new → redirect → reviewed`; correction revision `8f1c618`; classroom status `reconstructed example`.
- Do not show account passwords, API credentials, real feedback content, or user records. Use the short source excerpts rather than opening the production admin panel.
- No group inspection meeting, named approval, severity decision, or classroom defect ticket is claimed as historical fact.
