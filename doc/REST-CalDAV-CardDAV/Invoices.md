# EGroupware REST API for Invoices

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- Invoices application

All URLs used in this document are relative to EGroupware's REST API URL:
`https://egw.example.org/egroupware/groupdav.php/`

That means instead of `/invoices/` you have to use the full URL `https://egw.example.org/egroupware/groupdav.php/invoices/` replacing `https://egw.example.org/egroupware/` with whatever URL your EGroupware installation uses. 

### Hierarchy

`/invoices`  application collection (`POST` request to create a new invoice)
  + `/<invoice-id>` invoice object (`GET`, `PATCH`, `PUT` or `DELETE`, `POST` to create a new position)
    + `/positions` list of available positions, object with <position-id> <position-name> pairs
      + `/<position-id>` position (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
    + `/allowances` list of available allowances
      + `/<allowance-id>` position (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)

### State of the REST API implementation
- [x] create, update and finalize invoices
- [x] create, update and delete positions
- [x] create, update and delete document-level allowances or surcharges
> All not (yet) implemented REST API features are of course available in the Invoices user-interface in EGroupware.

### Invoices

Invoices are created via a `POST` request to the Invoices collection: `/invoices/`

Every invoice is a sub-collection in the above collection named by its ID.
With sufficient privileges, and as long they are of status `draft`, 
invoices can be edited with `PUT` or `PATCH` requests. 
Deleting with `DELETE` requests is only possible, if the invoice has NOT been issued!

**The following schema is used for JSON encoding of invoices**
```
{
    "ExchangedDocumentContext": {
        "BusinessProcessSpecifiedDocumentContextParameter": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
        "GuidelineSpecifiedDocumentContextParameter": "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"
    },
    "ExchangedDocument": {
        "ID": "DRAFT",
        "TypeCode": "380",
        "IssueDateTime": "20260313",
        "IncludedNote": [
            "Testrechnung",
            {
                "Content": "Note #1",
                "SubjectCode": "001"
            }
        ]
    },
    "SupplyChainTradeTransaction": {
        ...
    },
    "MetaData": {
        "Status": "draft",
        "Creator": "ralf@boulder.egroupware.org",
        "Created": "2025-07-08T15:35:17",
        "Modifier": "ralf@boulder.egroupware.org",
        "Modified": "2026-03-13T14:42:10",
        "Category": { "Categoryname": true },
        "Description": "jkhkjhl",
        "Path": "/invoices/39"
    }
}
```

| BT Number          | Description                                 | JSON-Path                                                                                                         |
|--------------------|---------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| **Invoice Data**   |                                             |                                                                                                                   |
| BT-1               | Invoice number                              | `/ExchangedDocument/ID`                                                                                           |
| BT-2               | Invoice issue date (YYYYmmdd)               | `/ExchangedDocument/IssueDateTime`                                                                                |
| BT-3               | Invoice type code e.g. 380                  | `/ExchangedDocument/TypeCode`                                                                                     |
| BT-22              | Notes (array of)                            | `/ExchangedDocument/IncludedNote/{n}/Content` (`/Content` can be skiped, if no SubjectCode)                       |    
| BT-21              | SubjectCode                                 | `/ExchangedDocument/IncludedNote/{n}/SubjectCode`                                                                 |     
|                    |                                             |                                                                                                                   |
| BT-23              | Business process type identifier            | `/ExchangedDocument/BusinessProcessSpecifiedDocumentContextParameter/BusinessProcessType`                         |
| BT-24              | Specification identifier                    | `/ExchangedDocument/SpecifiedExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ID`              |
|                    |                                             |                                                                                                                   |
| **References**     |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeAgreement`                                                     |
| BT-10              | Buyer reference (Leitweg-ID)                | + `BuyerReference`                                                                                                |
| BT-11              | Project reference                           | + `SpecifiedProcuringProject/ID`                                                                                  |                                                                
|                    | Project name                                | + `SpecifiedProcuringProject/Name`                                                                                |                                                                 
| BT-12              | Contract reference (contract nr)            | + `ContractReferencedDocument`                                                                                    |
| BT-13              | Buyer order reference (order nr)            | + `BuyerOrderReferencedDocument`                                                                                  |
| BT-14              | Seller order reference (order confirmation) | + `SellerOrderReferencedDocument`                                                                                 |
|                    |                                             |                                                                                                                   |
| **Seller**         |                                             | + `SellerTradeParty`                                                                                              |         
| BT-29              | Seller ID                                   | ++ `ID`                                                                                                           |
| BT-27              | Seller name                                 | ++ `Name`                                                                                                         |
| BT-31              | Seller VAT identifier                       | ++ `SpecifiedTaxRegistration/ID-VA`                                                                               |
| BT-32              | Seller tax registration identifier          | ++ `SpecifiedTaxRegistration/ID-FC`                                                                               |
| BT-35..40          | Seller address ...                          | ++ `PostalTradeAddress`                                                                                           |
| BT-35              | Seller address line 1                       | +++ `LineOne`                                                                                                     |
| BT-36              | Seller address line 2                       | +++ `LineTwo`                                                                                                     |
| BT-37              | Seller city                                 | +++ `CityName`                                                                                                    |
| BT-38              | Seller postal code                          | +++ `PostcodeCode`                                                                                                |
| BT-40              | Seller country code e.g. "DE"               | +++ `CountryID`                                                                                                   |
| BT-41..43          | Seller contact ...                          | ++ `DefinedTradeContact`                                                                                          |
| BT-41              | Seller contact name                         | +++ `PersonName`                                                                                                  |
| BT-41-0            | Seller contact department                   | +++ `DepartmentName`                                                                                              |
| BT-42              | Seller contact telephone                    | +++ `TelephoneUniversalCommunication`                                                                             |
| BT-43              | Seller contact email                        | +++ `EmailURIUniversalCommunication`                                                                              |
| BT-34              | Seller email                                | ++ `URIUniversalCommunication`                                                                                    |                                                                       |
|                    |                                             |                                                                                                                   |
| **Buyer**          |                                             | + `BuyerTradeParty`                                                                                               |               
| BT-46              | Buyer ID                                    | ++ `ID`                                                                                                           |
| BT-44              | Buyer name                                  | ++ `Name`                                                                                                         |
| BT-48              | Buyer VAT identifier                        | ++ `SpecifiedTaxRegistration/ID-VA`                                                                               |
| BT-47              | Seller tax registration identifier          | ++ `SpecifiedTaxRegistration/ID-FC`                                                                               |
| BT-50..55          | Buyer address ...                           | ++ `PostalTradeAddress`                                                                                           |
| BT-50              | Buyer address line 1                        | +++ `LineOne`                                                                                                     |
| BT-51              | Buyer address line 2                        | +++ `LineTwo`                                                                                                     |
| BT-52              | Buyer city                                  | +++ `CityName`                                                                                                    |
| BT-53              | Buyer postal code                           | +++ `PostcodeCode`                                                                                                |
| BT-55              | Buyer country code                          | +++ `CountryID`                                                                                                   |
| BT-56..58          | Buyer contact ...                           | ++ `DefinedTradeContact`                                                                                          |
| BT-56              | Buyer contact name                          | +++ `PersonName`                                                                                                  |
| BT-56-0            | Buyer contact department                    | +++ `DepartmentName`                                                                                              |
| BT-57              | Buyer contact telephone                     | +++ `TelephoneUniversalCommunication`                                                                             |
| BT-58              | Buyer contact email                         | +++ `EmailURIUniversalCommunication`                                                                              |
| BT-49              | Buyer email                                 | ++ `URIUniversalCommunication`                                                                                    |                                                                       |
|                    |                                             |                                                                                                                   |
| **OtherParties**   | See seller and buyer for attributes         | `/SupplyChainTradeTransaction/ApplicableHeaderTradeAgreement`                                                     |
| BT-                | Invoicee                                    | + `InvoiceeTradeParty`                                                                                            |               
| BT-                | Ship to                                     | + `ShipToTradeParty`                                                                                              |               
| BT-                | Ultimate ship to                            | + `UltimateShiptoTradeParty`                                                                                      |               
| BT-                | Product end user                            | + `ProductEndUserTradeParty`                                                                                      |               
| BT-                | Invoicer                                    | + `InvoicerTradeParty`                                                                                            |               
| BT-                | Ship from                                   | + `ShipFromTradeParty`                                                                                            |               
| BT-                | Payee                                       | + `PayeeTradeParty`                                                                                               |               
| BT-                | Tax representative                          | + `TaxRepresentativeTradeParty`                                                                                   |               
|                    |                                             |                                                                                                                   |
| **Invoice Lines**  |                                             | `/SupplyChainTradeTransaction/IncludedSupplyChainTradeLineItem/*/` (array of objects)                             |                                 
| BT-126             | Invoice line identifier (position number)   | + `AssociatedDocumentLineDocument/LineID` (`/ID` can be skipped)                                                  |
| BT-127             | Invoice line note                           | + `AssociatedDocumentLineDocument/IncludedNote` (`/Content` can be skipped)                                       |
| BT-129             | Invoice line quantity                       | + `SpecifiedLineTradeDelivery/BilledQuantity`                                                                     |
| BT-130             | Invoice line quantity UnitCode              | + `SpecifiedLineTradeDelivery/BilledQuantityUnitCode`                                                             |
| BT-153             | Invoice line item name                      | + `SpecifiedTradeProduct/Name`                                                                                    |
| BT-154             | Invoice line item description               | + `SpecifiedTradeProduct/Description`                                                                             |
| BT-159             | Invoice line item county of origin          | + `SpecifiedTradeProduct/OriginTradeCountry`                                                                      |
| BT-146             | Invoice line net price                      | + `SpecifiedLineTradeAgreement/NetPriceProductTradePrice`                                                         |                                                                                                 
| BT-                | VAT type code always "VAT"                  | + `SpecifiedLineTradeSettlement/ApplicableTradeTax/TypeCode` (can be skipped)                                     |
| BT-151             | VAT category code e.g. "S"                  | + `SpecifiedLineTradeSettlement/ApplicableTradeTax/CategoryCode`                                                  |
| BT-152             | VAT category rate in percent                | + `SpecifiedLineTradeSettlement/ApplicableTradeTax/RateApplicablePercent`                                         |
| BT-131             | Invoice line net amount (summarion)         | + `SpecifiedLineTradeSettlement/SpecifiedTradeSettlementLineMonetarySummation`                                    |
| BT-160             | Product charactarisation name               |                                                                                                                   |
| BT-161             | Product charactarisation value              |                                                                                                                   |
|                    | Line item allowances or surcharges          | + `SpecifiedLineTradeSettlement/SpecifiedTradeAllowanceCharge/*/` (max 100 objects)                               |
|                    | true: surcharge, false: allowance           | ++ `ChargeIndicator`                                                                                              |                                                            
| BT-136/141 (al/su) | Actual amount                               | ++ `ActualAmount`                                                                                                 |
| BT-137/142         | Basis amount                                | ++ `BasisAmount`                                                                                                  |
| BT-138/143         | Percent, if applicable                      | ++ `CalculationPercent`                                                                                           |                                                             
| BT-139/144         | Reason                                      | ++ `Reason`                                                                                                       |
| BT-140/155         | Reason code (optional)                      | ++ `ReasonCode`                                                                                                   |
|                    |                                             |                                                                                                                   |
| **Invoice Level**  | **Allowances or Surcharges**                | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement/SpecifiedTradeAllowanceCharge/*/` (max 100 objects) |
|                    | true: surcharge, false: allowance           | + `ChargeIndicator`                                                                                               |                                                            
| BT-92/99 (al/su)   | Actual amount                               | + `ActualAmount`                                                                                                  |
| BT-93/100          | Basis amount                                | + `BasisAmount`                                                                                                   |
| BT-94/101          | Percent, if applicable                      | + `CalculationPercent`                                                                                            |                                                             
| BT-97/104          | Reason                                      | + `Reason`                                                                                                        |
| BT-98/105          | Reason code (optional)                      | + `ReasonCode`                                                                                                    |
|                    | VAT category                                | + `CategoryTradeTax`                                                                                              |
| BT-95/102          | VAT category code e.g. "S"                  | ++ `CategoryCode`                                                                                                 |
| BT-96/103          | VAT category rate (percent)                 | ++ `RateApplicablePercent`                                                                                        |                                                                                    
|                    | Tax type code (optional, always "VAT")      | ++ `TypeCode`                                                                                                     |                                                                                             
|                    |                                             |                                                                                                                   |
| **Invoice Totals** |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement/SpecifiedTradeSettlementHeaderMonetarySummation`    |
| BT-106             | Sum of invoice line net amounts             | + `LineTotalAmount`                                                                                               |
| BT-107             | Sum of allowances                           | + `AllowanceTotalAmount`                                                                                          |
| BT-108             | Sum of surcharges                           | + `ChargeTotalAmount`                                                                                             |
| BT-109             | Invoice total amount without VAT            | + `TaxBasisTotalAmount`                                                                                           |
| BT-110             | Total VAT amount                            | + `TaxTotalAmount`                                                                                                |
| BT-110             | Total VAT amount currency ID e.g. "EUR"     | + `TaxTotalAmountCurrencyID`                                                                                      |
| BT-112             | Invoice total amount with VAT               | + `GrandTotalAmount`                                                                                              |
| BT-113             | Prepaid amount                              | + `TotalPrepaidAmount`                                                                                            |
| BT-114             | Rounding amount                             | + `GrandTotalAmount`                                                                                              |
| BT-115             | Due payable amount                          | + `DuePaybleAmount`                                                                                               |
|                    |                                             |                                                                                                                   |
| **VAT Summation**  |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement/ApplicableTradeTax/*/` (array of objects)           |
| BT-116             | VAT category taxable amount                 | + `BasisAmount`                                                                                                   |
| BT-117             | VAT category tax amount                     | + `ActualAmount`                                                                                                  |
| BT-118             | VAT category code e.g. "S"                  | + `CategoryCode`                                                                                                  |
| BT-119             | VAT category rate (percent)                 | + `CalculatedAmount`                                                                                              |
| BT-120             | VAT excemption reason                       | + `CalculatedAmount`                                                                                              |
| BT-121             | VAT excemption reason code                  | + `CalculatedAmount`                                                                                              |
|                    |                                             |                                                                                                                   |
| **Billing Period** |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement/BillingSpecifiedPeriod`                             |   
| BT-73              | Start date in format 102 YYYYmmdd           | + `StartDateTime`                                                                                                 |
| BT-74              | End date in format 102 YYYYmmdd             | + `EndDateTime`                                                                                                   |
|                    |                                             |                                                                                                                   |
| **Payment Terms**  |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement/SpecifiedTradePaymentTerms`                         |
| BT-20              | Description                                 | + `Description`  fixed value: `#SKONTO#TAGE=7#PROZENT=2.00#description`                                           |
| BT-9               | Due date in format 102 YYYYmmdd             | + `DueDateDateTime`                                                                                               |
|                    |                                             |                                                                                                                   |
| **Payment Data**   |                                             | `/SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement`                                                    |
| BT-5               | Invoice currencya code e.g. "EUR"           | +`InvoiceCurrencyCode`                                                                                            |
| BT-81              | Payment mean type code                      | + `SpecifiedTradeSettlementPaymentMeans/TypeCode`                                                                 |
|                    | SEPA transfer TypeCode=58                   | + ``                                                                                                              |
| BT-84              | Payment account identifier (IBAN)           | + `SpecifiedTradeSettlementPaymentMeans/PayeePartyCreditorFinancialAccount/IBANID`                                |
| BT-86              | Payment service provider identifier (BIC)   | + `SpecifiedTradeSettlementPaymentMeans/PayeePartyCreditorFinancialAccount/BICID`                                 |
|                    | Credit card TypeCode=48                     | + `SpecifiedTradeSettlementPaymentMeans/ApplicableTradeSettlementFinancialCard`                                   |
| BT-87              | Credit card number                          | ++ `ID`                                                                                                           |
| BT-88              | Credit card holder name                     | ++ `CardholderName`                                                                                               |
| BT-91              | SEPA mandate TypeCode=59                    | + `SpecifiedTradeSettlementPaymentMeans/PayerPartyDebtorFinancialAccount`                                         |
| BT-89              | Direct debit mandate ID                     | + `SpecifiedTradePaymentTerms/DirectDebitMandateID`                                                               |
| BT-90              | Bank assigned creditor identifyier          | + `CreditorReferenceID`                                                                                           |     
| BT-83              | Payment instruction note (purpose)          | + `/ExchangedDocument/SpecifiedTradeSettlementPaymentMeans/PaymentInstructionNote`                                |
|                    |                                             |                                                                                                                   |
| **MetaData**       |                                             | `/MetaData` attributes are readonly, unless otherwise noted                                                       |
| no bt-numbers      | Status: `draft`, `issued`, `imported`       | + `Status`  writable, but only certain transitions allowd                                                         |
|                    | Creator email of creator                    | + `Creator`                                                                                                       |
|                    | Created date/time                           | + `Created`                                                                                                       |
|                    | Last modifier email                         | + `Modifier`                                                                                                      |
|                    | Modified date/time                          | + `Modified`                                                                                                      |
|                    | Category object: { `cat-name`: true }       | + `Catgory` writable                                                                                              |
|                    | Description private notes, not in invoice   | + `Description` writable                                                                                          |
|                    | Path of object e.g. `/invoices/123`         | + `Path`                                                                                                          |

The response to the initial `POST` request to create an invoice contains a `Location` header to the newly created resource 
`/invoices/<invoice-id>`, which can be used to further modify the invoice with a `PUT` or `PATCH` request, 
read it's current state with a `GET` requests or use `DELETE` to remove it again.

### Positions
Each invoice-collection `/invoices/<invoice-id>/` containing positions as sub-collections.

Positions are created by sending a `POST` request to the invoices positions-collection with a JSON document (Content-Type: `application/json`) with metadata / object with the following attributes:

> Attributes marked as `(readonly)` should never be sent, they are only received in `GET` requests!

The response contains a `Location` header with the newly created position collection `/invoices/<invoice-id>/positions/<position-id>/`.

The main document and the JSON meta-data can always be updated by sending a `PUT` request with appropriate `Content-Type` header.

A position is removed with a `DELETE` request to its collection URL.

### Supported request methods and examples

> Most examples use optional `Prefer: return=representation` and `Accept: application/pretty+json` headers, 
> returning the complete objects to allow you to follow the changes made. 

> `GET` requests require only an `Accept: application/json` header, `POST`, `PATCH` or `PUT` requests 
> a `Content-Type: application/json` header.

#### **GET** to collections with an ```Accept: application/json``` header return all invoices
<details>
  <summary>Example: Getting all invoices (just the attributes from `ExchangedDocument` object)</summary>

```
curl https://example.org/egroupware/groupdav.php/invoices/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/invoices/1": {
      "ID": "R-2024-0001",
      "TypeCode": "380",
      "IssueDateTime": "20241210",
      "IncludedNote": [
        "Testrechnung"
      ]
    },
    "/invoices/3": {
      "ID": "R-2024-0002",
      "TypeCode": "380",
      "IssueDateTime": "20241213",
      "IncludedNote": [
        "Ihre Bestellung von gestern ;)"
      ]
    },
    ...
  }
}
```
</details>

Following GET parameters are supported to customize the returned properties:
- `props[]=<DAV-prop-name>` e.g. `props[]=displayname` to return only the name (multiple DAV properties can be specified)
  Default for invoice collections is to only return the ExchangedDocument object.
- ~~sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT~~ (not yet supported)
- ~~nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk~~

The GET parameter `filters` allows to filter or search for a pattern in the invoices:
- `filters[search]=<pattern>` searches for `<pattern>` in the invoices like the search in the GUI
- `filters[<attribute-name>]=<value>` filters by a DB-column name and value

<details>
   <summary>Example: Getting just (display-)name of all invoices</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/invoices/?props[]=displayname' -H "Accept: application/pretty+json" --user <username>

{
  "responses": {
    "/invoices/1": "Testrechnung",
    "/invoices/3": "Ihre Bestellung von gestern ;)",
    ...
  }
}
```
</details>

#### **GET**  requests with an ```Accept: application/pretty+json``` header can be used to retrieve single invoice / JsInvoice schema

<details>
   <summary>Example: GET request for a single invoice showcasing available fields</summary>

```
curl 'https://example.org/egroupware/groupdav.php/invoices/123' -H "Accept: application/pretty+json" --user <username>
{
    "ExchangedDocumentContext": {
        "BusinessProcessSpecifiedDocumentContextParameter": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
        "GuidelineSpecifiedDocumentContextParameter": "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"
    },
    "ExchangedDocument": {
        "ID": "DRAFT",
        "TypeCode": "380",
        "IssueDateTime": "20260316",
        "IncludedNote": [
            "Testrechnung",
            {
                "Content": "Note #1",
                "SubjectCode": "001"
            },
            "Note #2"
        ]
    },
    "SupplyChainTradeTransaction": {
        "IncludedSupplyChainTradeLineItem": [
            {
                "AssociatedDocumentLineDocument": { "LineID": "1" },
                "SpecifiedTradeProduct": { "Name": "Testlauf" },
                "SpecifiedLineTradeAgreement": { "NetPriceProductTradePrice": 100 },
                "SpecifiedLineTradeDelivery": {
                    "BilledQuantity": 4.5,
                    "BilledQuantityUnitCode": "C62"
                },
                "SpecifiedLineTradeSettlement": {
                    "ApplicableTradeTax": {
                        "TypeCode": "VAT",
                        "CategoryCode": "S",
                        "RateApplicablePercent": 19
                    },
                    "SpecifiedTradeSettlementLineMonetarySummation": 450
                }
            },
            ...
            {
                "AssociatedDocumentLineDocument": { "LineID": "8" },
                "SpecifiedTradeProduct": { "Name": "Testlaufen" },
                "SpecifiedLineTradeAgreement": { "NetPriceProductTradePrice": 100 },
                "SpecifiedLineTradeDelivery": {
                    "BilledQuantity": 2,
                    "BilledQuantityUnitCode": "XZZ"
                },
                "SpecifiedLineTradeSettlement": {
                    "ApplicableTradeTax": {
                        "TypeCode": "VAT",
                        "CategoryCode": "S",
                        "RateApplicablePercent": 19
                    },
                    "SpecifiedTradeAllowanceCharge": [
                        {
                            "ChargeIndicator": true,
                            "CalculationPercent": 5,
                            "BasisAmount": 200,
                            "ActualAmount": 10,
                            "Reason": "Irgendwas"
                        },
                        {
                            "CalculationPercent": 3,
                            "BasisAmount": 200,
                            "ActualAmount": 6,
                            "Reason": "Extra ;)"
                        },
                        {
                            "BasisAmount": 200,
                            "ActualAmount": 50,
                            "Reason": "Lump sum"
                        }
                    ],
                    "SpecifiedTradeSettlementLineMonetarySummation": 154
                }
            }
        ],
        "ApplicableHeaderTradeAgreement": {
            "BuyerReference": "Leitweg-ID (bt-10)",
            "SellerTradeParty": {
                "ID": "Seller-ID (bt-29)",
                "Name": "EGroupware GmbH",
                "DefinedTradeContact": {
                    "PersonName": "Ralf Becker",
                    "DepartmentName": "IT-Abteilung",
                    "TelephoneUniversalCommunication": "+49 123 4567890",
                    "EmailURIUniversalCommunication": "rb@egroupware.org"
                },
                "PostalTradeAddress": {
                    "PostcodeCode": "67663",
                    "LineOne": "Leibnizstraße 17",
                    "CityName": "Kaiserlautern",
                    "CountryID": "DE"
                },
                "URIUniversalCommunication": "info@egroupware.org",
                "URIUniversalCommunicationSchemeID": "EM"
            },
            "BuyerTradeParty": {
                "ID": "buyer-id",
                "Name": "Outdoor Unlimited Training GmbH",
                "DefinedTradeContact": {
                    "PersonName": "Birgit Becker",
                    "TelephoneUniversalCommunication": "+49 123 4567890",
                    "EmailURIUniversalCommunication": "bb@example.org"
                },
                "PostalTradeAddress": {
                    "PostcodeCode": "12345",
                    "LineOne": "Some street",
                    "CityName": "Somewhere"
                },
                "URIUniversalCommunication": "bt49@email.com",
                "SpecifiedTaxRegistration": {
                    "ID-VA": "UST-ID (bt-48)",
                    "ID-FC": "SteuerNr"
                },
                "URIUniversalCommunicationSchemeID": "EM"
            },
            "SellerOrderReferencedDocument": "BestellBestätigung (bt-14)",
            "BuyerOrderReferencedDocument": "BestellNr (bt-13)",
            "ContractReferencedDocument": "VertragsNr (bt-12)",
            "SpecifiedProcuringProject": {
                "ID": "Projectnummer (bt-11)",
                "Name": "Project Reference"
            }
        },
        "ApplicableHeaderTradeSettlement": {
            "InvoiceCurrencyCode": "EUR",
            "ApplicableTradeTax": [
                {
                    "CalculatedAmount": 1548.08,
                    "TypeCode": "VAT",
                    "BasisAmount": 8147.8,
                    "CategoryCode": "S",
                    "RateApplicablePercent": 19
                },
                {
                    "CalculatedAmount": 21,
                    "TypeCode": "VAT",
                    "BasisAmount": 300,
                    "CategoryCode": "S",
                    "RateApplicablePercent": 7
                },
                {
                    "CalculatedAmount": 0,
                    "TypeCode": "VAT",
                    "BasisAmount": 500,
                    "CategoryCode": "Z",
                    "RateApplicablePercent": 0
                }
            ],
            "BillingSpecifiedPeriod": {
                "StartDateTime": "20241130",
                "EndDateTime": "20241230"
            },
            "SpecifiedTradeAllowanceCharge": [
                {
                    "ChargeIndicator": true,
                    "CalculationPercent": 10,
                    "BasisAmount": 7398,
                    "ActualAmount": 739.8,
                    "Reason": "Sonstwas",
                    "CategoryTradeTax": {
                        "TypeCode": "VAT",
                        "CategoryCode": "S",
                        "RateApplicablePercent": 19
                    }
                },
                {
                    "BasisAmount": 7398,
                    "ActualAmount": 10,
                    "Reason": "Erstbestellung",
                    "CategoryTradeTax": {
                        "TypeCode": "VAT",
                        "CategoryCode": "S",
                        "RateApplicablePercent": 19
                    }
                },
                {
                    "ChargeIndicator": true,
                    "BasisAmount": 7398,
                    "ActualAmount": 20,
                    "Reason": "Fracht",
                    "CategoryTradeTax": {
                        "TypeCode": "VAT",
                        "CategoryCode": "S",
                        "RateApplicablePercent": 19
                    }
                }
            ],
            "SpecifiedTradePaymentTerms": {
                "Description": "#SKONTO#TAGE=7#PROZENT=2.00#\nZahlbar bis {{InvoicePaymentDateNetto}} netto, oder bis {{InvoicePaymentDateskonto}} mit {{InvoicePaymentPercentskonto}}% Skonto.",
                "DueDateDateTime": "20260415",
                "DirectDebitMandateID": "MANDATE-2025/000001 bt-89"
            },
            "SpecifiedTradeSettlementHeaderMonetarySummation": {
                "LineTotalAmount": 8198,
                "ChargeTotalAmount": 759.8,
                "AllowanceTotalAmount": -10,
                "TaxBasisTotalAmount": 8947.8,
                "TaxTotalAmount": 1569.08,
                "GrandTotalAmount": 10516.88,
                "TotalPrepaidAmount": 0,
                "DuePayableAmount": 10516.88,
                "TaxTotalAmountCurrencyID": "EUR"
            }
        }
    },
    "MetaData": {
        "Status": "draft",
        "Creator": "ralf@boulder.egroupware.org",
        "Created": "2025-07-08T15:35:17",
        "Modifier": "ralf@boulder.egroupware.org",
        "Modified": "2026-03-13T14:42:10",
        "Category": { "Some name": true },
        "Description": "jkhkjhl",
        "Path": "/invoices/39"
    }
}
```
</details>

#### **POST** requests to collection with a ```Content-Type: application/json``` header add a new invoice
> Location header in response gives URL of new invoice

<details>
   <summary>Example: POST request to create a new invoice</summary>

```
cat <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/invoices/' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "ExchangedDocument": {
        "TypeCode": "380",
        "IncludedNote": [
            "Ralf's Test Invoice"
        ]
    },   
    "SupplyChainTradeTransaction": {
        "ApplicableHeaderTradeAgreement": {
            "BuyerTradeParty": {
                "Name": "Example Buyer GmbH",
                "PostalTradeAddress": {
                    "PostcodeCode": "12345",
                    "LineOne": "Some street 123",
                    "CityName": "Somewhere"
                },
                "URIUniversalCommunication": "test@example.com"
            }
        }
    },
    "MetaData": {
        "Status": "draft"
    }
}
EOF

HTTP/2 201 Created
content-type: application/json
x-dav-powered-by: EGroupware 26.1 CalDAV/CardDAV/GroupDAV server
location: /egroupware/groupdav.php/invoices/53

{
    "ExchangedDocumentContext": {
        "BusinessProcessSpecifiedDocumentContextParameter": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
        "GuidelineSpecifiedDocumentContextParameter": "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"
    },
    "ExchangedDocument": {
        "ID": "DRAFT",
        "TypeCode": "380",
        "IssueDateTime": "20260316",
        "IncludedNote": [
            "Ralf's Test Invoice"
        ]
    },
    "SupplyChainTradeTransaction": {
        "ApplicableHeaderTradeAgreement": {
            "SellerTradeParty": {
                "Name": "EGroupware GmbH",
                "DefinedTradeContact": {
                    "PersonName": "Ralf Becker",
                    "DepartmentName": "IT-Abteilung",
                    "TelephoneUniversalCommunication": "+49 123 4567890",
                    "EmailURIUniversalCommunication": "rb@egroupware.org"
                },
                "PostalTradeAddress": {
                    "PostcodeCode": "67663",
                    "LineOne": "Leibnizstraße 17",
                    "CityName": "Kaiserlautern",
                    "CountryID": "DE"
                },
                "URIUniversalCommunication": "info@egroupware.org",
                "URIUniversalCommunicationSchemeID": "EM"
            },
            "BuyerTradeParty": {
                "Name": "Example Buyer GmbH",
                "PostalTradeAddress": {
                    "PostcodeCode": "12345",
                    "LineOne": "Some street 123",
                    "CityName": "Somewhere"
                },
                "URIUniversalCommunication": "test@example.com",
                "URIUniversalCommunicationSchemeID": "EM"
            }
        },
        "ApplicableHeaderTradeSettlement": {
            "InvoiceCurrencyCode": "EUR",
            "SpecifiedTradeSettlementHeaderMonetarySummation": {
                "LineTotalAmount": 0,
                "ChargeTotalAmount": 0,
                "AllowanceTotalAmount": 0,
                "TaxBasisTotalAmount": 0,
                "TaxTotalAmount": 0,
                "GrandTotalAmount": 0,
                "TotalPrepaidAmount": 0,
                "DuePayableAmount": 0,
                "TaxTotalAmountCurrencyID": "EUR"
            }
        }
    },
    "MetaData": {
        "Status": "draft",
        "Creator": "ralf@boulder.egroupware.org",
        "Created": "2026-03-16T10:33:44",
        "Modifier": "ralf@boulder.egroupware.org",
        "Modified": "2026-03-16T10:33:44",
        "Path": "/invoices/53"
    }
}
```
</details>

#### **POST** requests to invoices-collection to add a new PDF or XML invoice with a `Content-Type: application/pdf` or `Content-Type: application/xml` header
> Location header in response gives URL of new invoice

> Please note: curl requires `--data-binary` when uploading binary content like PDF documents!

> Not yet implemented, use the UI!

<details>
   <summary>Example: POST request to create/import a invoice from a Zugferd PDF or X-Rechnung XML</summary>

```
curl -i -X POST 'https://example.org/egroupware/groupdav.php/invoices/' --data-binary @/path/to/test.pdf \
  -H 'Content-Type: application/pdf' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/invoices/123

{
    ...
}
```
</details>

#### **PATCH** requests with a ```Content-Type: application/json``` header to change e.g. the invoice description

<details>
   <summary>Example: PATCH request to update an invoice</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/invoice/123' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json'
{
    "MetaData/Description": "This is the new description ;)"
}
EOF

HTTP/1.1 204 No Content
```
</details>

#### **POST** requests to position-collection of an invoice to add a position ```Content-Type: application/json``` header
> Location header in response gives URL of new position

<details>
   <summary>Example: POST request to create a new position</summary>

```
CAT <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/invoices/123/positions/' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "SpecifiedTradeProduct": { "Name": "Testläufe" },
    "SpecifiedLineTradeAgreement": { "NetPriceProductTradePrice": 100 },
    "SpecifiedLineTradeDelivery": {
        "BilledQuantity": 5,
        "BilledQuantityUnitCode": "C62"
    },
    "SpecifiedLineTradeSettlement": {
        "ApplicableTradeTax": {
            "TypeCode": "VAT",
            "CategoryCode": "S",
            "RateApplicablePercent": 19
        }
    }
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/invoices/123/234

HTTP/2 201
server: nginx/1.28.2
date: Mon, 16 Mar 2026 10:58:15 GMT
content-type: application/json
expires: Thu, 19 Nov 1981 08:52:00 GMT
cache-control: no-store, no-cache, must-revalidate
pragma: no-cache
x-dav-powered-by: EGroupware 26.1 CalDAV/CardDAV/GroupDAV server
location: /egroupware/groupdav.php/invoice/53/positions/234
content-location: https://boulder.egroupware.org/egroupware/groupdav.php/invoices/53/positions/
content-encoding: identity
x-webdav-status: 201 Created
x-content-type-options: nosniff

{
    "AssociatedDocumentLineDocument": { "LineID": "1" },
    "SpecifiedTradeProduct": { "Name": "Testläufe" },
    "SpecifiedLineTradeAgreement": { "NetPriceProductTradePrice": 100 },
    "SpecifiedLineTradeDelivery": {
        "BilledQuantity": 5,
        "BilledQuantityUnitCode": "C62"
    },
    "SpecifiedLineTradeSettlement": {
        "ApplicableTradeTax": {
            "TypeCode": "VAT",
            "CategoryCode": "S",
            "RateApplicablePercent": 19
        },
        "SpecifiedTradeSettlementLineMonetarySummation": 500
    }
}
```
</details>

#### **DELETE** request to remove an invoice
> Only invoices with status `draft`, `imported` or `template` can be removed!

<details>
   <summary>Example: DELETE request to remove an invoice</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/invoices/123 --user <user>

HTTP/1.1 204 No Content
```
</details>

#### **DELETE** request to remove a position

> Only invoices with status `draft` or `template` can be modified in that way!

<details>
   <summary>Example: DELETE request to remove a position</summary>

```
curl -iL -X DELETE https://example.org/egroupware/groupdav.php/invoices/123/positions/234 --user <user>

HTTP/1.1 204 No Content
```
</details>

#### **DELETE** request to remove an allowance or surcharge

> Only invoices with status `draft` or `template` can be modified in that way!

<details>
   <summary>Example: DELETE request to remove an allowance or surcharge</summary>

```
curl -iL -X DELETE https://example.org/egroupware/groupdav.php/invoices/123/allowances/345 --user <user>

HTTP/1.1 204 No Content
```
</details>