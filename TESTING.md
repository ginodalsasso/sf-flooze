# TESTING

À lire avant d'écrire ou modifier des tests.

---

## Hiérarchie

```
tests/
├── Unit/           → Logique isolée (mocks)
│   ├── Service/    → OllamaClientTest, TransactionServiceTest
│   └── Entity/     → CategoryTest (validation hiérarchie)
├── Integration/    → DB réelle + fixtures minimales
│   ├── Repository/
│   └── EventListener/
└── Functional/     → HTTP, forms, workflows
    ├── Controller/
    └── Workflow/   → ReceiptToTransactionWorkflowTest
```

---

## Unit

- **Mock toutes les dépendances externes** (HTTP, DB, Ollama, S3).
- Une méthode = un test.
- Nommage : `test{Method}{Scenario}` — `testExtractFromImageReturnsAmount`, `testCreateTransactionRejectsNegativeWhenIncome`.
- Pour les services qui appellent du HTTP : `MockHttpClient` + `MockResponse`.
- Pour Doctrine : `EntityManagerInterface` mocké, jamais de DB.

---

## Integration

- DB de test dédiée — `.env.test` avec `DATABASE_URL=...sf_flooze_test`.
- **Jamais de DB mockée en integration** — sinon le test n'a aucune valeur.
- Fixtures **minimales** : créer uniquement ce dont le test a besoin, pas une "base de référence" partagée.
- Rollback par transaction après chaque test (`KernelTestCase` + `DAMA\DoctrineTestBundle` ou wrap manuel).
- Couvre :
  - **Repositories** — queries complexes, filtre `deletedAt IS NULL`, scope par `space`.
  - **Event listeners** — `TimestampListener` (auto created_at/updated_at), `SoftDeleteListener` (preRemove), `AutoCategoryListener` (Ollama call sur prePersist Transaction), `LinkedTransactionListener` (auto-création Transaction depuis RentPayment/LoanPayment).

---

## Functional

- `WebTestCase`.
- Happy path + 1 cas d'erreur par endpoint (4xx ou redirection forbidden).
- Asserts : code de réponse, redirections, présence de flash messages, présence de l'entité en DB.
- **Ne pas tester les bytes PDF** — assert uniquement le `Content-Type: application/pdf` et le `Content-Disposition`.
- Workflows complets (e.g. `ReceiptToTransactionWorkflowTest`) : enchaîner upload → OCR → review → persist, vérifier l'état final en DB.
- Auth : utiliser `loginUser($user)` du `WebTestCase`, jamais de bypass de sécurité.
- Multi-tenant : créer 2 users avec 2 spaces différents, vérifier qu'un user ne peut pas accéder aux entités d'un autre space (`SpaceScopeVoter` doit retourner 403).

---

## Anti-patterns

- DB mockée en integration test.
- Fixtures "globales" partagées entre tous les tests — couplage fort, tests qui cassent en cascade.
- Asserter sur le HTML rendu plutôt que sur la DB ou le code de réponse.
- Tester le détail d'implémentation (méthodes privées, ordre d'appel) plutôt que le comportement observable.
- Tests qui dépendent de l'ordre d'exécution.
- Mocker l'objet sous test lui-même.