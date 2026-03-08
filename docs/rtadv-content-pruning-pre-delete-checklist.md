# RTADV Content Pruning Pre-Delete Checklist

Use this checklist before executing any suggested pruning batch such as `DEL-01` or `MRG-01`.

## Batch Scope

- Confirm the batch label matches the intended execution set.
- Confirm every URL in the batch is a `post`, not a `page` or `project`.
- Confirm the batch size is small enough to observe safely.
  - Recommended: 10 posts per batch.

## Data Gate

- Confirm `GA4 12m` is filled for every post in the batch.
- Confirm `GSC clicks 12m` is filled for every post in the batch.
- Confirm `GSC impressions 12m` is filled for every post in the batch.
- Confirm `backlinks` is filled for every post in the batch.
- Confirm the post is older than 12 months.

## Value Gate

- Confirm no important backlinks are present.
- Confirm no meaningful referring domains are present if available.
- Confirm commercial value is not `high`.
- Confirm the page is not used as a sales proof, brand proof, or trust asset.
- Confirm the page is not still receiving meaningful impressions or long-tail demand.

## Routing Gate

- For `Delete + 301`, confirm a destination URL exists.
- Confirm the destination URL is topically aligned.
- Confirm the destination URL is live and indexable.
- Confirm the destination URL is not itself scheduled for deletion.
- For `Merge + 301`, confirm the receiving page has absorbed the useful content first.

## Internal Link Gate

- Confirm important internal links pointing to the old URL are known.
- Confirm key internal links will be updated if needed.
- Confirm the URL is not a cluster hub or orphan-prevention node.

## Execution Gate

- Confirm the action is one of:
  - `Delete + 301`
  - `Merge + 301`
  - `Keep + Noindex`
  - `Keep + Update`
- Confirm `redirect_target` is filled for all `301` actions.
- Confirm `batch_label` is saved.
- Confirm notes explain why the action was chosen.

## Post-Launch Monitoring Plan

- Prepare a 7-day check.
- Prepare a 28-day check.
- Prepare a 56-day check.
- Monitor:
  - GSC coverage and indexing
  - 404/redirect issues
  - destination page impressions and clicks
  - unexpected ranking drops on related topics

## Stop Conditions

Stop the batch if any of these are true:

- Redirect destination is unclear.
- The post has meaningful backlinks.
- The post still has meaningful search demand.
- The post has unresolved business or brand value.
- The receiving page is weak, thin, or off-topic.

## Batch Sign-Off

- Reviewer:
- Date:
- Batch label:
- Approved actions count:
- Deferred actions count:
- Risks noted:
- Follow-up review date:
