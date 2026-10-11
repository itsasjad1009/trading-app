# Market-data rights review — 2026-10-11

Sources reviewed: [official market-only endpoint FAQ](https://github.com/binance/binance-spot-api-docs/blob/master/faqs/market_data_only.md), [REST documentation](https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md), [streams](https://github.com/binance/binance-spot-api-docs/blob/master/web-socket-streams.md), [Binance terms](https://www.binance.com/en/terms), [Vision dataset project](https://github.com/binance/binance-public-data).

| Planned use | Classification | Evidence / action |
|---|---|---|
| Unauthenticated technical access to documented public market endpoints | Confirmed permitted as an API access mechanism | Official FAQ documents no API key requirement; this is not a commercial data license. |
| Private local display / storage / development testing | Not yet verified as a comprehensive contractual right | Technical endpoint access was tested. Applicable account, entity, jurisdiction and API terms require review; no blanket commercial permission inferred. |
| Display or redistribution to customers | Not yet verified | Obtain written confirmation before setting both public-redistribution and rights-verified flags. |
| Commercial derived data | Not yet verified | Review retention, ownership, attribution and derived-data rights with provider. |
| Financial-product pricing / settlement benchmark | Not yet verified | Seek explicit written permission and product classification. Remains technically disabled. |
| Bypassing access restrictions or bans | Prohibited by implementation policy | No alternate host/provider circumvention. Honor 429/418 cooldowns. |

The terms page retrieved during this review exposed its site shell rather than the complete agreement. A readable binding API-specific agreement was not established. Do not treat successful requests as evidence of licensing permission. Ownership, mandatory attribution, permitted retention and geographic eligibility remain unresolved. UI attribution identifies Binance Spot as the source; this does not satisfy an unverified license by itself.

Vision downloadable datasets are a separate product; this adapter uses the documented REST API, not archive downloads. The supplied prompt's noncommercial dataset-license claim was not independently verified from a readable current license and is not generalized to these APIs. Coinbase rules are not Binance permissions and no Coinbase adapter is used.

Default public/commercial redistribution is disabled. Only loopback host + loopback request IP in local/testing can access private development data. Public mode requires both MARKET_DATA_PUBLIC_REDISTRIBUTION and MARKET_DATA_RIGHTS_VERIFIED; changing these flags is an operator responsibility after documented permission, not an automatic rights determination. Production ingestion without both flags is refused.

Pakistan or another operating jurisdiction requires current legal review of financial-product classification, licensing and consumer/AML requirements before accepting any real funds. No regulatory authorization is asserted here.
