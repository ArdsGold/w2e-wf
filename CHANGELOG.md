# Changelog

## r2.0.1 — Marker system cleanup

- Made `h1`, `h2`, `h3`, `p`, `repeat`, and `step` the canonical Elementor marker vocabulary.
- Preserved `h1NonRepeat`, `sectionTitleNonRepeat`, `hNonRepeat`, `pNonRepeat`, `repeatableItem`, and `stepNumber` as automatic legacy aliases.
- Renamed internal marker constants to the canonical `H2_ID` and `H3_ID` names; deprecated constant aliases remain for compatibility with code that references them.
- Renamed the old DOCX block fallback method to make clear that it is a DOCX data compatibility path, not an Elementor marker path.
- Updated logs and documentation to use canonical marker terminology.
- Corrected the plugin/package version to `r2.0.1`.

## r2.0.0 — Wolf Forge Elementor Page Generator

- Rebranded the plugin to **Wolf Forge Elementor Page Generator**.
- Updated plugin version to `r2.0.0`.
- Authors: **Macky Villafuerte, Arden Guinto**.
- Added compatibility comments explaining why the existing `WFEBPG_` and `wfebpg_` identifiers remain in place.
- Added documentation for template markers, architecture, and development workflow.
- Preserved the existing generation features and WordPress data identifiers.

See the previous release notes preserved in the prior plugin README/build history. The r2.0.0 release is based on the existing v1.15.0 codebase and retains its generation features, including saved JSON templates, Auto Detect, repeatable sections, image pools, template image replacement, queue processing, generated-page tracking, and admin QA tools.
