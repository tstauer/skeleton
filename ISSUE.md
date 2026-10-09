**Title:** teaser_selection: edited item of one selection overwrites the teaser of the same page in another selection

| Q            | A      |
|--------------|--------|
| Sulu Version | 3.0.10 |
| PHP Version  | 8.4    |

#### Actual Behavior

A page renders two `teaser_selection` properties that both reference the same target page, e.g. a teaser list in the
content and a link list in a footer snippet. In the footer, the item is edited in the admin: own title, image and
description removed. The stored item then looks like this:

```json
{"id": "<target-uuid>", "type": "pages", "title": "Footer link title", "mediaId": null, "description": null}
```

The teaser in the content is not edited (`{"id": "<target-uuid>", "type": "pages"}`), but on the website it shows the
footer's title and has neither image nor description. Which selection wins depends only on the order in which they are
resolved.

#### Expected Behavior

The overrides of an item only apply to that item. The unedited teaser shows the excerpt title, description and image
of the target page.

#### Steps to Reproduce

Reproducer based on `sulu/skeleton` 3.0

No local PHP needed, everything runs in Docker:

```bash
docker compose up -d
docker compose run --rm php composer install
docker compose run --rm php php bin/adminconsole sulu:build dev --destroy --no-interaction
```

`src/DataFixtures/TeaserReproducerFixtures.php` creates a target page with an excerpt (title, description, image) and the
page "Teaser reproducer" with the template `teaser_reproducer` (two `teaser_selection` properties, see above).

- Website: http://localhost:8000/teaser-reproducer – both sections show "Footer link title", the first one without image
  and description
- Admin: http://localhost:8000/admin (user `admin`, password `admin`), page "Teaser reproducer" – the form lists the first
  teaser correctly as "Excerpt title" with image, the preview next to it shows "Footer link title" twice

The same as a test, it fails on 3.0.10:

```bash
docker compose run --rm php php bin/phpunit
```

```
-'Excerpt title'
+'Footer link title'
```

#### Cause

`TeaserSelectionPropertyResolver` merges the item data into the loaded `Teaser` in place:

```php
resourceCallback: static function(Teaser $resource) use ($itemData): Teaser {
    return $resource->merge($itemData);
}
```

but the `Teaser` instance is shared:

- `ResolvableResourceLoader::loadResources()` loads each resource id (`pages::<uuid>`) once and hands the same object to
  every metadata identifier that requested it,
- `CachedResourceLoader` returns the same object for the rest of the request (e.g. when a snippet is resolved later).

`Teaser::merge()` mutates `$this`, so every item callback changes the teaser of all other selections that reference the
same page.

#### Possible Solutions

Merge into a copy:

```php
return (clone $resource)->merge($itemData);
```

With this change the reproducer passes. `upstream/TeaserSelectionSharedInstanceTest.php` in the reproducer is a unit
test against the Sulu classes only (`TeaserSelectionPropertyResolver`, `ResolvableResourceLoader`,
`CachedResourceLoader`, `TeaserResourceLoader`), suitable for `packages/content/tests/Unit/` in a PR.
