# Silverstripe Funnelback search module

This module provides an interface to query a [Funnelback](https://www.squiz.net/funnelback) [collection](https://docs.squiz.net/funnelback/archive/15.24/collections/collection-overview/index.html).

## Features
### Current features
- Search a single Funnelback collection by one or more keywords
- Present paginated search results to users
- Has been tested and known to work with Funnelback version 15.

You can see this module in action on the [Whaikaha - Ministry of Disabled People website](https://whaikaha.govt.nz/search?query=accessibility).

### Still to do
- Identify a search result in the database and provide the most up-to-date content for the results page
- Ability to filter or facet information (reliant on Funnelback supporting this)

### What this module won't try to do
- Index content into a Funnelback collection. Instead, use the [silverstripe-search-service module](https://github.com/silverstripe/silverstripe-search-service)
and add your own [Funnelback search service](https://github.com/silverstripe/silverstripe-search-service/blob/2/docs/en/customising_add_search_service.md), OR have Funnelback crawl your website directly.

## Installation
Install this module via Composer:
```bash
$ composer require madmatt/silverstripe-funnelback
```

### Requirements
You need to be using Silverstripe CMS 4.10 or newer, no other dependencies should matter.

## Usage
Once you've installed the module, you'll need to do two things:
1. Configure the necessary environment variables.
2. Create & integrate your search form.

### Step 1: Configuring the necessary environment variables
This module needs the following environment variables configured:

* `SS_FUNNELBACK_URL`: The base URL to the Funnelback API endpoint, without any trailing slashes or path. For example, https://example-uat-search.squiz.cloud
* `SS_FUNNELBACK_USERNAME`: The username provided by Funnelback.
* `SS_FUNNELBACK_PASSWORD`: The password provided by Funnelback.
* `SS_FUNNELBACK_COLLECTION`: The name of the collection (e.g. example-collection).

Once configured, the module can be used to perform search requests.

### Step 2: Create & integrate your search form
You may already have some search infrastructure in place. If so, adapt these instructions as needed. This assumes you have nothing setup yet.

Create a new controller for your search page - `app/src/Controllers/SearchController.php`:

```php
<?php
namespace App\Controllers;

use Madmatt\Funnelback\SearchService;

class SearchController extends \PageController
{
    private static $dependencies = [
        'searchService' => '%$' . SearchService::class
    ];

    public SearchService $searchService;

    public function index(HTTPRequest $request)
    {
        $keyword = $request->getVar('q');
        $start = $request->getVar('start') ?? 0;

        // If a keyword has been supplied, perform a search and return the results.
        // Otherwise, don't bother performing an empty search.
        if ($keyword) {
            return [
                'Query' => DBField::create_field('Varchar', $keyword)
                'Results' => $this->searchService->search($keyword, $start),
            ];
        } else {
            return [];
        }
    }
}
```

Next, register your new controller via a `Director` route, for example in `app/_config/routes.yml`:

```yaml
---
Name: app-routes
After:
  - '#rootroutes'
  - '#coreroutes'
---
SilverStripe\Control\Director:
  rules:
    'search': 'App\Controllers\SearchController'
```

Create your search results template (for example `themes/<theme>/templates/App/Search/Layout/Search.ss`):

```html
<main>
    $SearchForm

    <% if $Results || $Query %>
    <section>
        <h2>Results for "$Query"</h2>
    </section>

    <section>
        <% if $Results %>
            <h3>Displaying $Results.FirstItem - $Results.LastItem results of $Results.TotalItems</h3>

            <ul>
                <% loop $Results %>
                <li>
                    <h3><a href="$Link">$Title</a></h3>
                    <p>$Summary.RAW</p>
                </li>
                <% end_loop %>
            </ul>

            <% if $Results.MoreThanOnePage %>
            <nav aria-label="pagination">
                <ul>
                    <% if $Results.NotFirstPage %>
                        <li><a href="$Results.PrevLink">Previous</a></li>
                    <% end_if %>

                    <% loop $Results.PaginationSummary %>
                        <% if $CurrentBool %>
                        <li>$PageNum <span class="sr-only">(current)</span></li>
                        <% else %>
                            <% if $Link %>
                                <li><a href="$Link">$PageNum</a></li>
                            <% else %>
                                <li>...</li>
                            <% end_if %>
                        <% end_if %>
                    <% end_loop %>

                    <% if $Results.NotLastPage %>
                        <li><a href="$Results.NextLink">Next</a></li>
                    <% end_if %>
                </ul>
            </nav>
            <% end_if %>
        <% else %>
            <h3>No search results found for "$Query"</h3>
        <% end_if %>
    </section>
    <% end_if %>
</main>
```

Now, assuming that Funnelback has already crawled your website, you should be able to visit `http://your-website/search?q=testing` and have the website query Funnelback and return results for you.

On your `PageController` class, add the following `SearchForm` method so that you can output a search form on every page of your website:

```php
<?php

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;

class PageController extends ContentController
{
    public function SearchForm(): Form
    {
        $form = Form::create(
            $this,
            __FUNCTION__,
            FieldList::create([
                TextField::create('q', 'Search query')
            ]),
            FieldList::create([
                FormAction::create('search')
            ])
        );

        $form
            ->setFormAction('/search') // Override the standard form action URL to always be /search
            ->setFormMethod('GET', true) // Ensure the form sends the search query in the URL so it can be bookmarked and cached etc
            ->disableSecurityToken(); // Turn off CSRF protection for this form, it's not required unless you have sensitive or private search results

        return $form;
    }
}
```

Finally, add this into your template:

```html
<header>
    $SearchForm
</header>
```

Provided everything is configured correctly, you should now have everything you need to get Funnelback search working on your website.

## Optional configuration
Optional configuration options are listed below.

## Code of Conduct
When having discussions about this module, please adhere to the [Silverstripe Community Code of Conduct](https://docs.silverstripe.org/en/project_governance/code_of_conduct).
