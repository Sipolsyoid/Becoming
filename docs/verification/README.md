# Photo-verifier smoke results

Run date: 8 October 2026. Provider: local Ollama, model `gemma3:4b`.

The initial report passed three of four labelled cases. The model withheld approval for a clearly visible logo because it questioned whether the image counted as a photograph. The system instruction was clarified: directly visible requested objects count as supporting evidence; camera authorship is outside this assessment. The cases and expected decisions stayed unchanged.

The refined report passed all four cases, taking approximately 5–7 seconds per case with the model already loaded. This is not a cold-start or multi-user performance benchmark. Both reports are retained, including the failed baseline.

The three negative cases check unrelated reading evidence, an unverifiable running distance, and instruction-like habit text. All cases reuse one public logo image. They do not establish accuracy on real activity photos or general resistance to prompt injection. Before making broader claims, add consented and labelled positive/negative activity photos and report their failures as well as their successes.

Reproduce with `php artisan photos:evaluate --output=docs/verification/real-model.json`. The command writes no habit/check-in records. Automated command tests use a mocked provider, while these JSON reports were produced by the real local model.
