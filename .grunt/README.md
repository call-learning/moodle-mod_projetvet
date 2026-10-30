# Local Sass build

This folder contains the module-local Grunt entrypoint used when building the plugin on its own.

## Tasks

- `grunt sass`
  - compile `scss/styles.scss` to `styles.css`
- `grunt rawscss`
  - lint the source SCSS files under `scss/`
- `grunt scss`
  - run `rawscss`, then compile, then normalize selector formatting in the generated CSS
- `grunt`
  - same as `grunt scss`

## Build model

The source of truth is the SCSS tree under `scss/`.

`styles.css` is generated output.
It should not be edited by hand.

The plugin uses a local `.stylelintignore` so the generated `styles.css` is not linted as source.
That avoids false failures caused by Sass output formatting.

## Selector formatting caveat

Sass can emit selector lists on a single line, for example:

```scss
&:hover, &:focus {
    text-decoration: none;
}
```

This is valid SCSS and compiles correctly, but the generated CSS may not match the same line-wrapping style that source linting expects.

To keep the generated `styles.css` stable and readable, the build post-processes selector lists and splits them across lines when there is more than one selector.

That means:

- linting should happen on the source SCSS, not the generated CSS
- generated CSS is a build artifact
- comma-separated selectors in the SCSS are fine, but their compiled CSS may be rewritten for consistency
