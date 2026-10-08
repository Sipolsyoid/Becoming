# Photo-verifier smoke results

Run date: 8 October 2026. Provider: local Ollama, model `gemma3:4b`.

The initial report passed three of four labelled cases. The model withheld approval for a clearly visible logo because it questioned whether the image counted as a photograph. The system instruction was clarified: directly visible requested objects count as supporting evidence; camera authorship is outside this assessment. The cases and expected decisions stayed unchanged.

The refined report passed all four cases, taking approximately 5–7 seconds per case with the model already loaded. This is not a cold-start or multi-user performance benchmark. Both reports are retained, including the failed baseline.

The three negative cases check unrelated reading evidence, an unverifiable running distance, and instruction-like habit text. All cases reuse one public logo image. They do not establish accuracy on real activity photos or general resistance to prompt injection. Before making broader claims, add consented and labelled positive/negative activity photos and report their failures as well as their successes.

Reproduce with `php artisan photos:evaluate --output=docs/verification/real-model.json`. The command writes no habit/check-in records. Automated command tests use a mocked provider, while these JSON reports were produced by the real local model.

## Activity-photo evaluation

`php artisan photos:evaluate tests/Fixtures/activity-photo-evaluation.json --images-root="C:/path/to/photos" --repeat=2 --output=docs/verification/activity-model.json` uses five owner-supplied activity photos without copying them into Git. Expected filenames are listed in the manifest. The synthetic instruction-card image is committed; real activity photos stay outside the repository. Reports retain SHA-256 hashes, labelled expectations, verdicts, timing, false approvals/refusals and errors; they exclude absolute private image paths.

The first 30-run evaluation on 8 October passed 27 runs and exposed three false approvals: both one-hour coding cases and one of two running-distance cases with instruction-like habit text. The cases cover visible coding/drinking/phone-use/rest evidence, unrelated images, unverifiable duration/distance/quantity, habit-text instructions and one synthetic image instruction attack. These five supplied photos and one synthetic card are a small targeted dataset, not a representative population; repeated runs are not independent examples. The original failures are retained in activity-model-2026-10-08.json.
