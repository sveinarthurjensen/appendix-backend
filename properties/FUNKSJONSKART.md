# Funksjonskart – Appendix Properties (70 funksjoner)

Kilde: `functions.invoke` i `src/` (frontend), `base44/workflows/*.jsonc`, og kryssreferanser i `base44/`.

| Gruppe | Antall | Hva |
|---|---|---|
| A | 30 | Kalles direkte fra sidene – må finnes i Laravel før appen kan kobles helt fra Base44 |
| B | 11 | Planlagte jobber – blir `schedule()` + Jobs i Laravel |
| C | 6 | Eksterne endepunkt (webhooks, iCal, offentlig API) – egne ruter i Laravel |
| D | 15 | Identitet: OIDC, BankID, QR-innlogging, WebAuthn – skrives om samlet i fase 6 |
| E | 8 | Base44-spesifikt (backup til OneDrive, RLS-audit, synk til master) – erstattes av Postgres-backup/Laravel, eller slettes |


## A Daglig bruk i frontend

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `outlookMailbox` | 10 | stor (8 kB) |  |
| `sendSms` | 5 | liten (2 kB) |  |
| `vaultManager` | 4 | middels (5 kB) |  |
| `manageInvitation` | 3 | stor (7 kB) |  |
| `sendWebhook` | 3 | liten (1 kB) |  |
| `grantMinSideAccess` | 2 | middels (2 kB) |  |
| `logInviteEvent` | 2 | liten (1 kB) |  |
| `createUserInvite` | 1 | middels (4 kB) |  |
| `replyToPortalCase` | 1 | middels (2 kB) |  |
| `fetchPropertyData` | 1 | middels (3 kB) |  |
| `provisionUserInvite` | 1 | middels (4 kB) |  |
| `postPortalReply` | 1 | liten (2 kB) |  |
| `fetchImagesFromUrl` | 1 | middels (5 kB) |  |
| `generateAiLetterResponse` | 1 | liten (2 kB) |  |
| `validateInviteToken` | 1 | liten (1 kB) |  |
| `brregOppslag` | 1 | stor (9 kB) |  |
| `exportDocumentForSigning` | 1 | middels (2 kB) |  |
| `sendNotification` | 1 | middels (3 kB) |  |
| `importPropertyIcal` | 1 | liten (2 kB) |  |
| `getTenantPortalData` | 1 | liten (1 kB) |  |
| `sendEmailSmtp` | 1 | liten (1 kB) |  |
| `getStreetViewImage` | 1 | liten (1 kB) |  |
| `analyzePropertyMarket` | 1 | middels (4 kB) |  |
| `exportDocumentToWord` | 1 | middels (2 kB) |  |
| `sendUserInvitation` | 1 | stor (6 kB) |  |
| `receiveChatMessage` | 1 | liten (0 kB) |  |
| `getMyPortalData` | 1 | stor (7 kB) |  |
| `resolveInviteCode` | 1 | liten (1 kB) |  |
| `suggestLetterChanges` | 1 | liten (2 kB) |  |
| `autoApproveInvitedUser` | 1 | middels (3 kB) |  |

## B Planlagte jobber (scheduler)

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `runSecurityAudit` | 1 | stor (6 kB) | workflow |
| `kpiRentAdjustmentReminder` | 1 | stor (6 kB) | workflow |
| `contractStatusGuard` | – | middels (3 kB) | workflow |
| `checkPaymentReminders` | – | middels (3 kB) | workflow |
| `contractDeadlineReminders` | – | stor (7 kB) | workflow |
| `sendToHolding` | – | stor (11 kB) | workflow |
| `overvakNettsider` | – | middels (5 kB) | workflow |
| `azureOneDriveBackup` | – | middels (3 kB) | workflow |
| `syncAllToMaster` | – | middels (3 kB) | workflow |
| `welcomeOnUserRegistered` | – | middels (3 kB) | workflow |
| `generateScheduledMaintenance` | – | middels (4 kB) | workflow |

## C Eksterne endepunkt / webhooks

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `mottaHenvendelse` | – | stor (10 kB) | webhook fra nettside/kontaktskjema |
| `behandleHenvendelse` | – | middels (2 kB) | kalles av mottaHenvendelse |
| `approveAndSendUserInvite` | – | middels (3 kB) | ? |
| `exportPropertyIcal` | – | liten (1 kB) | iCal-URL for Airbnb/Booking |
| `sendUserMessage` | – | middels (3 kB) | ? |
| `offentligeUtleieobjekter` | – | middels (3 kB) | offentlig API (nettside) |

## D Identitet (BankID/QR/WebAuthn) – fase 6

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `registerWebAuthnCredential` | 4 | middels (5 kB) |  |
| `pollQRSession` | 3 | liten (1 kB) |  |
| `generateQRSession` | 3 | middels (2 kB) |  |
| `signicatBankIdStepUp` | 3 | stor (10 kB) |  |
| `approveQRSession` | 3 | stor (7 kB) |  |
| `signDocumentBankId` | 2 | stor (9 kB) |  |
| `bankIdStepUpFromQR` | 1 | middels (4 kB) |  |
| `preauthBridgeFromQR` | – | liten (1 kB) | QR-innlogging (mobil) |
| `stampBankIdVerification` | – | liten (1 kB) | BankID-flyt |

## D Identitet (OIDC) – fase 6

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `oidcUserinfo` | – | liten (1 kB) | OIDC |
| `oidcToken` | – | middels (3 kB) | OIDC |
| `oidcJwks` | – | liten (0 kB) | OIDC |
| `oidcAuthorize` | – | stor (6 kB) | OIDC-endepunkt (andre apper logger inn via denne) |
| `oidcSignicatCallback` | – | stor (16 kB) | OIDC/BankID-callback fra Signicat |
| `oidcDiscovery` | – | liten (0 kB) | OIDC |

## E Base44-spesifikt / erstattes av plattformen

| Funksjon | Kall i frontend | Størrelse | Merknad |
|---|---|---|---|
| `downloadBackup` | 1 | liten (1 kB) |  |
| `backfillContractVersions` | 1 | middels (3 kB) |  |
| `backupDatabase` | 1 | middels (4 kB) |  |
| `exportContractsToHolding` | 1 | stor (11 kB) |  |
| `getFeatureFlags` | – | liten (0 kB) | feature flags |
| `adminStatus` | – | middels (3 kB) | admin-status (trolig Base44-side) |
| `receiveLocationsData` | – | middels (4 kB) | webhook fra andre apper |
| `nightlyBackupToOneDrive` | – | middels (4 kB) | backup (erstattes av pgbackup) |


## Anbefalt rekkefølge (spor B)

1. **A, små og middels først** – sendSms, sendEmailSmtp, sendWebhook, sendNotification, brregOppslag, fetchPropertyData, getStreetViewImage, fetchImagesFromUrl, importPropertyIcal, vaultManager, getMyPortalData, getTenantPortalData, postPortalReply, replyToPortalCase, receiveChatMessage, grantMinSideAccess, generateAiLetterResponse, suggestLetterChanges, exportDocumentToWord, exportDocumentForSigning, analyzePropertyMarket. Dette er ~20 funksjoner som hver er 1–3 timer i PHP; integrasjonsklientene (Sveve SMS, Brreg, Graph/Outlook, Google Maps, OpenAI) lages én gang og gjenbrukes.
2. **outlookMailbox** (10 kall, stor) – egen Graph-klient i Laravel. Viktig for daglig bruk.
3. **Invitasjonsflyten** (createUserInvite, provisionUserInvite, manageInvitation, sendUserInvitation, validateInviteToken, resolveInviteCode, autoApproveInvitedUser, logInviteEvent, welcomeOnUserRegistered) – henger sammen, tas som én pakke.
4. **B – planlagte jobber** – rett fram, 1 dag.
5. **C – eksterne endepunkt** – nye URL-er må registreres hos avsenderne (nettside, Airbnb/Booking, andre apper).
6. **D – identitet** – fase 6, med Laravel som OIDC-provider. Størst, men også der Base44-avhengigheten er dypest.
7. **E** – slettes eller erstattes; backup er allerede løst av pgbackup-containeren.

Inntil hver gruppe er flyttet, går `functions.invoke` for de gjenværende videre til Base44 gjennom shimen.
