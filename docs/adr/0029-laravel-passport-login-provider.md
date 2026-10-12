# Laravel Passport is a supported login provider; Music Party is not an OAuth server

The triage found Passport login missing from the provider catalogue (diff-13, feat-80). It has since been restored in code: `app/Domain/Admin/ProviderCatalogue.php`, `app/Domain/Identity/SocialProviders/LaravelPassportProvider.php` and `socialiteproviders/laravelpassport` in `composer.json`. ADR-0017 already lists it. ADR-0005 rejects "OAuth2 for third-party apps through Passport", and readers took that as ruling out the login provider. The two are different things.

Decision:
- Music Party logs users in against an external Laravel Passport server. The admin sets its host URL, client id, client secret and display name.
- It does this through `socialiteproviders/laravelpassport`. The ADR-0017 rule on social providers is unchanged.
- Music Party does not install `laravel/passport` and does not act as an OAuth server. The rejection in ADR-0005 stands.

Consequences: an instance can use another Laravel application as its identity source, with no consent flow or client registration of its own to maintain. ADR-0017 is consistent with this and is not annotated. ADR-0005 gets a note so the rejection is read as being about running an OAuth server.
