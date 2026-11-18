# Fixtures

- `title.toml`: the title the issue fixtures inherit from.
- `*/valid/*.toml`: must load and pass with no errors. A line `# warn: <field>` names a field
  expected to draw a warning. Each one, saved by the editor, must also pass `nbnp-ingest check`.
- `*/invalid/*.toml`: must be refused. The first line, `# expect: <field>`, names the field the
  error is about, or the key that makes the file unsupported.
