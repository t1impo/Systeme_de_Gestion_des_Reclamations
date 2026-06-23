# Pipeline CI/CD du projet "Systeme de Gestion des Reclamations"


## Table des matieres

1. [Vue d'ensemble](#1-vue-densemble)
2. [Flux complet : du commit a l'utilisateur](#2-flux-complet--du-commit-a-lutilisateur)
3. [Catalogue des outils et services](#3-catalogue-des-outils-et-services)
4. [Analyse fichier par fichier](#4-analyse-fichier-par-fichier)
5. [Securite — defense en profondeur](#5-securite--defense-en-profondeur)
6. [Haute disponibilite](#6-haute-disponibilite)
7. [Couts et strategie d'economie](#7-couts-et-strategie-deconomie)
8. [Choix d'architecture et trade-offs assumes](#8-choix-darchitecture-et-trade-offs-assumes)
9. [Glossaire](#9-glossaire)

---

## 1. Vue d'ensemble

### 1.1 Objectif du pipeline

Le pipeline CI/CD automatise tout le cycle de vie de l'application PHP, **depuis
le commit du developpeur jusqu'a la mise a disposition de la nouvelle version
en production**, sans aucune action manuelle.

**Concretement** : a chaque push sur la branche `master`, le pipeline :

1. Verifie la qualite du code (lint PHP, HTML, CSS)
2. Construit une image Docker de l'application
3. Pousse cette image dans un registre prive
4. Demarre une base MySQL temporaire et execute la suite de tests
5. Deploie automatiquement la nouvelle image sur l'environnement de production
6. Verifie que les nouvelles instances sont en bonne sante avant de retirer les anciennes

Si l'une de ces etapes echoue, le pipeline s'arrete et la production
n'est **pas** mise a jour.

### 1.2 CI vs CD — la distinction

| Sigle | Sens | Couverture dans ce projet |
|-------|------|---------------------------|
| **CI** (Continuous Integration) | Verifier automatiquement que chaque changement ne casse rien (lint, build, tests) | Jobs `setup`, `build`, `test` |
| **CD** (Continuous Deployment) | Mettre en production automatiquement chaque changement valide | Job `deploy` + infrastructure AWS Terraform |

Dans ce projet, on parle de **Continuous Deployment** (et pas seulement
Continuous Delivery) car **aucune intervention humaine** n'est requise entre
le push et la mise en production effective.

### 1.3 Schema d'architecture global

```
                         GITHUB
                            │
                            │ push master
                            ▼
                  ┌──────────────────────┐
                  │   GitHub Actions     │
                  │  (.github/workflows  │
                  │       /main.yml)     │
                  └──────────┬───────────┘
                             │ 4 jobs en sequence
                             │ setup -> build -> test -> deploy
                             ▼
       ┌─────────────────────────────────────────┐
       │  Authentification OIDC vers AWS         │
       │  (sans cle long-vie)                    │
       └─────────────────────┬───────────────────┘
                             │
                             ▼
   ┌───────────────────────────────────────────────────────┐
   │                 AWS — us-east-1                       │
   │                                                       │
   │   ┌──────────┐                                        │
   │   │   ECR    │  ← push image build au job 'build'     │
   │   │ php-app  │                                        │
   │   └─────┬────┘                                        │
   │         │ pull au demarrage des taches                │
   │         ▼                                             │
   │   ┌──────────────────────────────────────────────┐    │
   │   │             VPC 10.0.0.0/16                  │    │
   │   │                                              │    │
   │   │  Internet -> ALB (sg-alb) -> ECS Service     │    │
   │   │                              (sg-app)        │    │
   │   │                              2 taches HA     │    │
   │   │                              sur 2 AZs       │    │
   │   │                                  │           │    │
   │   │                                  │ 3306      │    │
   │   │                                  ▼           │    │
   │   │                            RDS MySQL         │    │
   │   │                            (sg-db)           │    │
   │   │                            password gere     │    │
   │   │                            par Secrets       │    │
   │   │                            Manager           │    │
   │   └──────────────────────────────────────────────┘    │
   │                                                       │
   │   State Terraform stocke dans S3 + lock DynamoDB      │
   │                                                       │
   └───────────────────────────────────────────────────────┘
                             ▲
                             │
                             │ terraform apply (manuel ou CI infra)
                             │
                  ┌──────────┴───────────┐
                  │   Terraform local    │
                  │     (dossier         │
                  │     `infra/`)        │
                  └──────────────────────┘
```

### 1.4 Decoupage en 4 jobs

Le pipeline GitHub Actions est organise en **4 jobs sequentiels** :

| Job | Role | Duree typique |
|-----|------|---------------|
| `setup` | Installer PHP, Composer et les dependances. Verifier la presence de Bootstrap. | ~1 min |
| `build` | Linter PHP/HTML/CSS, construire l'image Docker, la pousser dans ECR. | ~2 min |
| `test` | Demarrer MySQL, importer le schema, lancer un conteneur applicatif local, executer PHPUnit. | ~3 min |
| `deploy` | Deployer la nouvelle image sur ECS Fargate (uniquement sur push master). | ~3 min |

La dependance entre jobs est definie par `needs: <job_precedent>` :
`setup -> build -> test -> deploy`. Si un job amont echoue, les jobs en aval
ne s'executent pas.

---

## 2. Flux complet : du commit a l'utilisateur

### 2.1 Etape par etape

```
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 1 — Developpeur                                               │
│ git commit -m "feat: nouvelle page"                                 │
│ git push origin master                                              │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 2 — GitHub recoit le push                                     │
│ - Detecte le trigger 'on: push: branches: [master]'                 │
│ - Demarre le workflow CI/CD Pipeline                                │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 3 — Job 'setup' (runner ubuntu-latest)                        │
│ - actions/checkout : recupere le code                               │
│ - shivammathur/setup-php : installe PHP 8.2                         │
│ - actions/cache : cache du dossier Composer                         │
│ - composer install : installe les dependances PHP                   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 4 — Job 'build' (runner ubuntu-latest)                        │
│ - php -l   : lint syntaxique sur les .php                           │
│ - html-validate : lint HTML                                         │
│ - stylelint : lint CSS                                              │
│ - configure-aws-credentials (OIDC) : assume role AWS sans cle       │
│ - amazon-ecr-login : login Docker au registre ECR                   │
│ - docker build : construit l'image avec le Dockerfile               │
│ - docker push : pousse l'image avec 2 tags (SHA court + latest)     │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 5 — Job 'test' (runner ubuntu-latest)                         │
│ - service container : demarre un MySQL 8 temporaire                 │
│ - importe bd.sql dans MySQL                                         │
│ - configure-aws-credentials (OIDC) + ECR login                      │
│ - docker run : demarre le conteneur applicatif local sur :8080      │
│ - boucle health check : attend HTTP 200 sur /login_page.php         │
│ - phpunit : execute 5 suites de tests                               │
│ - si echec : 'docker logs app' pour faciliter le debug              │
└─────────────────────────────────────────────────────────────────────┘
                              │
                    [tous les jobs verts ?]
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 6 — Job 'deploy' (uniquement si push, pas si PR)              │
│ - configure-aws-credentials (OIDC) : assume role deploy             │
│ - aws ecs describe-task-definition : recupere la def courante       │
│ - amazon-ecs-render-task-definition : modifie le champ image        │
│   pour pointer sur le tag SHA fraichement push                      │
│ - amazon-ecs-deploy-task-definition :                               │
│     1. Register une nouvelle revision de la task def                │
│     2. Update le service ECS pour pointer dessus                    │
│     3. Attend la stabilite (rolling update healthy)                 │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 7 — ECS effectue un rolling deployment                        │
│ - Demarre 2 nouvelles taches avec la nouvelle image                 │
│ - Attend qu'elles passent healthy au target group de l'ALB          │
│   (3 health checks consecutifs OK sur /index/login_page.php)        │
│ - Bascule le trafic ALB vers les nouvelles taches                   │
│ - Eteint les 2 anciennes taches                                     │
│ - Min healthy 50% + Max 200% : zero downtime                        │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Etape 8 — Utilisateur final                                         │
│ Recharge la page : sert deja la nouvelle version, sans aucune       │
│ interruption percue                                                 │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Triggers — quand le pipeline se declenche

```yaml
on:
  push:
    branches: [ master ]    # tous les jobs sauf deploy
  pull_request:
    branches: [develop]     # idem
```

Le job `deploy` a une condition supplementaire : `if: github.event_name == 'push'`.
Cela signifie :

- **Push direct sur master** : les 4 jobs s'executent (build + test + deploy).
- **Pull Request vers develop** : seuls setup, build, test s'executent. Le deploiement n'a pas lieu — c'est volontaire, on ne deploie en prod que ce qui est merge dans master.

---

## 3. Catalogue des outils et services

> Cette section decrit chaque brique technologique du pipeline, **independamment**
> du reste. Elle peut etre lue dans n'importe quel ordre comme un dictionnaire.

### 3.1 Cote source

#### Git

Systeme de gestion de versions distribue. Permet de tracer chaque modification
du code, de creer des branches pour les fonctionnalites, et de fusionner des
contributions multiples.

**Role dans le pipeline** : chaque commit produit un **SHA** (identifiant
unique de 40 caracteres). Le pipeline en garde les 7 premiers caracteres
(`GITHUB_SHA::7`) comme **tag d'image Docker** — c'est ce qui rend chaque
deploiement tracable a un commit precis.

#### GitHub

Plateforme d'hebergement de depots Git proposee par Microsoft. Au-dela du
stockage, elle fournit :

- **Pull Requests** : mecanisme de revue de code
- **Secrets** : stockage chiffre de credentials accessibles aux workflows
- **Actions** : moteur d'execution de pipelines CI/CD (voir 3.2)
- **Identite OIDC** : permet l'authentification federe vers AWS (voir 3.4.j)

### 3.2 Cote CI — outils du runner GitHub Actions

#### GitHub Actions

Service de CI/CD integre a GitHub. Un **workflow** est un fichier YAML place
dans `.github/workflows/`. Il decrit :

- **Quand** se declencher (`on:`)
- **Quoi** executer (`jobs:` -> `steps:`)
- **Ou** s'executer (`runs-on:` — typiquement `ubuntu-latest`)

Chaque execution se fait sur un **runner**, une machine virtuelle ephemere
fournie par GitHub (gratuite pour les depots publics, 2000 min/mois pour les
prives). A chaque job, on a une VM fraiche : pas d'etat persistant entre
les jobs sauf via les **artifacts** ou le **cache**.

**Concepts cles** :

| Element | Description |
|---------|-------------|
| `workflow` | Le fichier YAML entier |
| `job` | Une unite d'execution sur 1 runner. Les jobs peuvent s'enchainer (`needs:`) ou tourner en parallele. |
| `step` | Une commande dans un job (`run:`) ou l'invocation d'une action reutilisable (`uses:`) |
| `action` | Code reutilisable (souvent en TypeScript ou Docker) publie sur le marketplace. Ex: `actions/checkout@v4` |
| `secret` | Variable chiffree stockee au niveau du depot, accessible via `${{ secrets.X }}` |
| `permissions` | Controle ce que le **GITHUB_TOKEN** du workflow peut faire (lire le code, ecrire un commentaire de PR, generer un JWT OIDC, etc.) |

#### `actions/checkout@v4`

Action officielle qui clone le depot dans le filesystem du runner. Sans elle,
le code source n'est pas accessible.

#### `shivammathur/setup-php@v2`

Action communautaire qui installe la version de PHP demandee + ses extensions
(mbstring, pdo, pdo_mysql, gd, zip, curl). Plus rapide qu'une installation
manuelle via apt.

#### `actions/setup-node@v4`

Action officielle qui installe Node.js. Utilisee pour installer **html-validate**
et **stylelint** via npm (eux-memes ecrits en JavaScript).

#### `actions/cache@v4`

Met en cache un dossier entre deux executions du workflow. Ici, le cache porte
sur `~/.composer/cache`, ce qui accelere significativement les builds suivants
(les memes dependances ne sont pas re-telechargees).

La cle de cache contient `hashFiles('**/composer.lock')` : si `composer.lock`
change, la cle change, et le cache est invalide.

#### `aws-actions/configure-aws-credentials@v4`

Action officielle d'AWS. Configure les credentials AWS pour le job en cours.
Deux modes possibles :

1. **Cles statiques** (deprecate dans ce projet) :
   ```yaml
   aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
   aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
   ```
2. **OIDC** (utilise dans ce projet) :
   ```yaml
   role-to-assume: arn:aws:iam::023036696848:role/cd-php-app-github-actions-deploy
   ```

En mode OIDC, l'action :
1. Demande un **JWT** au GitHub OIDC IdP (l'intermediaire d'identite interne de GitHub)
2. Appelle `sts:AssumeRoleWithWebIdentity` sur AWS avec ce JWT
3. Recupere des credentials temporaires (1h)
4. Les expose au reste du job via les variables `AWS_*`

#### `aws-actions/amazon-ecr-login@v2`

Recupere un token d'authentification ECR via l'API AWS, et configure Docker
pour pouvoir `docker push` / `docker pull` sur le registre prive.

#### `aws-actions/amazon-ecs-render-task-definition@v1`

Prend en entree un fichier JSON de **task definition** (telecharge via
`aws ecs describe-task-definition`) et modifie le champ `image` du conteneur
specifie. Renvoie un nouveau fichier JSON (chemin dans
`steps.render-task-def.outputs.task-definition`).

Pourquoi ne pas modifier directement avec `sed` ? Parce que la task definition
contient ~50 champs imbriques avec des regles JSON precises. L'action gere
proprement la mutation sans casser le format.

#### `aws-actions/amazon-ecs-deploy-task-definition@v2`

Prend en entree :
1. Le JSON rendered (image mise a jour)
2. Le nom du cluster et du service

Effectue :
1. `ecs:RegisterTaskDefinition` → cree une nouvelle **revision** de la task def
2. `ecs:UpdateService` → fait pointer le service sur la nouvelle revision
3. `ecs:DescribeServices` → polling jusqu'a `service.deployments[0].rolloutState == "COMPLETED"` (ou timeout)

#### Composer

Gestionnaire de dependances pour PHP, equivalent de npm/yarn pour Node.js
ou pip pour Python. Lit `composer.json`, telecharge les paquets dans `vendor/`,
genere un autoloader PSR-4.

Dans le pipeline : utilise pour installer les dependances de l'application
(notamment **PHPUnit** pour les tests).

#### PHPUnit

Framework de test unitaire pour PHP. Le pipeline execute 5 suites organisees
par type :

- `tests/Unit/DbHelperTest.php` — tests unitaires
- `tests/Unit/testlogin.php` — autres tests unitaires
- `tests/Integration/DbConnectionTest.php` — tests d'integration (connexion DB reelle)
- `tests/Functional/HttpTest.php` — tests fonctionnels (HTTP via curl)
- `tests/Security/SecurityTest.php` — tests de securite

Si l'une de ces suites echoue, le job `test` echoue, et le `deploy` ne se
lance pas.

#### Docker (cote runner GitHub Actions)

Le runner `ubuntu-latest` a Docker preinstalle. Le pipeline l'utilise pour :

- **Job build** : `docker build` pour construire l'image applicative depuis le `Dockerfile` du depot, puis `docker push` vers ECR
- **Job test** : `docker run` pour demarrer un conteneur applicatif a partir de l'image fraichement build, et le tester avec curl + phpunit
- **Service container MySQL** : GitHub Actions a une feature `services:` qui demarre des conteneurs auxiliaires (ici mysql:8) automatiquement, attache au reseau du job

### 3.3 Cote Infrastructure as Code (IaC)

#### Terraform

Outil developpe par HashiCorp pour decrire l'infrastructure de maniere
declarative dans des fichiers `.tf` (langage HCL). Plutot que de cliquer dans
la console AWS, on ecrit :

```hcl
resource "aws_vpc" "main" {
  cidr_block = "10.0.0.0/16"
}
```

Et `terraform apply` cree (ou met a jour) la ressource via les API AWS.

**Concepts cles** :

| Element | Description |
|---------|-------------|
| `provider` | Plugin qui sait parler a un cloud (AWS, GCP, Azure, etc.). Ce projet utilise `hashicorp/aws ~> 5.0`. |
| `resource` | Une ressource a creer/mettre a jour (ex: `aws_vpc`, `aws_security_group`). |
| `data` | Une donnee a lire depuis le cloud (ex: lire un depot ECR existant). |
| `variable` | Parametre du module (ex: `aws_region`, `vpc_cidr`). |
| `output` | Valeur exposee apres un apply (ex: `alb_dns_name`). |
| `locals` | Valeurs calculees, reutilisables dans plusieurs ressources. |
| `state` | Fichier `.tfstate` qui memorise l'etat reel de l'infrastructure. **Indispensable** : sans lui, Terraform ne sait pas ce qu'il a deja cree. |

**Workflow Terraform** :

1. `terraform init` : telecharge les providers, configure le backend
2. `terraform plan` : compare l'etat declare (les .tf) avec l'etat reel
   (le .tfstate + le cloud), et affiche les differences a appliquer
3. `terraform apply` : execute les changements
4. `terraform destroy` : supprime tout ce qui est gere par ce state

#### Backend S3 + DynamoDB

Le **state Terraform** est stocke dans un bucket S3 (`tf-state-cd-php-app-023036696848`)
au lieu d'un fichier local. Avantages :

- **Persistance** : si on perd l'ordinateur, le state survit
- **Partage** : plusieurs personnes peuvent travailler sur la meme infra
- **Chiffrement** : `encrypt = true` chiffre le state au repos
- **Versioning** : le bucket a le versioning S3 actif, on peut revenir en arriere

La table **DynamoDB** (`terraform-locks-cd-php-app`) sert au **lock distribue** :
quand un `apply` commence, Terraform ecrit une ligne dans la table.
Tout autre `apply` concurrent voit la ligne et echoue avec un message clair,
**empechant la corruption du state par concurrence**.

### 3.4 Cote AWS — services cloud utilises

#### 3.4.a Amazon ECR (Elastic Container Registry)

Registre Docker prive d'AWS, equivalent prive de DockerHub. **L'image
applicative buildee par la CI y est stockee**, taguee par SHA de commit
(immutable de fait) et `latest`.

**Pourquoi pas DockerHub ?**

1. ECR s'authentifie par IAM, pas par login/password (plus sur)
2. Le pull depuis ECS dans la meme region est **gratuit en transfert**
3. ECR support `scanOnPush=true` pour detecter les CVE dans l'image

**Cout** : 500 MB gratuits/mois, puis $0.10/GB/mois. L'image PHP fait ~190 MB,
gratuit dans notre cas.

#### 3.4.b Amazon VPC (Virtual Private Cloud)

Reseau prive isole dans AWS. CIDR `10.0.0.0/16` = 65 536 adresses IP disponibles.
**Toutes les ressources reseau du projet vivent dedans**.

Decoupe en **4 sous-reseaux** (subnets) repartis sur **2 zones de disponibilite**
(us-east-1a et us-east-1b) :

| Subnet | CIDR | AZ | Type | Usage |
|--------|------|----|----|-------|
| `public-us-east-1a` | 10.0.1.0/24 | us-east-1a | Public | ALB + taches ECS |
| `public-us-east-1b` | 10.0.2.0/24 | us-east-1b | Public | ALB + taches ECS |
| `private-us-east-1a` | 10.0.11.0/24 | us-east-1a | Prive | RDS |
| `private-us-east-1b` | 10.0.12.0/24 | us-east-1b | Prive | RDS |

**Public vs prive** :

- Un subnet est "public" s'il a une **route vers l'Internet Gateway** dans
  sa route table.
- Un subnet est "prive" sans route IGW. Les ressources qu'on y place n'ont
  **aucun chemin** vers internet.

#### 3.4.c Internet Gateway

"Porte" entre le VPC et internet. Attachee a la route table publique
(`0.0.0.0/0 -> igw-XXX`). **Gratuit**.

#### 3.4.d Security Groups (SG)

Pare-feu **statefull au niveau ENI** (Elastic Network Interface). Chaque
ressource reseau a au moins 1 SG attache.

Differences cles vs pare-feu classique :

- Pas de regle "deny" explicite (par defaut tout est interdit, on ouvre seulement)
- Reference par **SG ID** au lieu d'IP : "autoriser depuis le SG X" est plus
  strict et resilient que "autoriser depuis 10.0.1.0/24"

**Les 3 SG du projet** :

| SG | Ingress autorise | Logique |
|----|------------------|---------|
| `cd-php-app-alb-sg` | 80/tcp depuis `0.0.0.0/0` | Le ALB est expose a internet |
| `cd-php-app-app-sg` | 80/tcp depuis le SG `alb` UNIQUEMENT | Les taches ne sont **pas** joignables directement, meme avec une IP publique |
| `cd-php-app-db-sg` | 3306/tcp depuis le SG `app` UNIQUEMENT | La base n'est jamais accessible directement par internet ni par d'autres ressources |

Cette chaine `internet -> alb -> app -> db` est le **principe du moindre privilege
reseau** : chaque couche n'autorise que la couche immediatement en amont.

#### 3.4.e Amazon RDS for MySQL

Service de base de donnees relationnelle managee. AWS gere :
- Le serveur MySQL (patches, OS, demarrage)
- Les backups automatiques quotidiens
- Le **chiffrement au repos** (storage_encrypted = true)
- L'integration avec Secrets Manager pour le master password

Configuration choisie :

| Param | Valeur | Effet |
|-------|--------|-------|
| `engine_version` | MySQL 8.0 | Compatible avec ton code |
| `instance_class` | `db.t3.micro` | 2 vCPU / 1 GB RAM, eligible Free Tier 750h/mois |
| `allocated_storage` | 20 GB gp2 | Gratuit (Free Tier) |
| `multi_az` | false | Single-AZ : une seule replique, moins cher mais pas de failover automatique en cas de panne d'AZ |
| `publicly_accessible` | false | Pas d'IP publique, **inaccessible** depuis internet |
| `db_subnet_group` | private-1a + private-1b | Place RDS dans les subnets prives |
| `vpc_security_group_ids` | sg-db | Filtre l'acces a 3306 depuis sg-app uniquement |
| `manage_master_user_password` | true | Le password est **genere et tourne** automatiquement par AWS, jamais en clair |

**Pourquoi 2 subnets prives alors que single-AZ ?** Parce que RDS exige au
moins 2 subnets dans son "DB subnet group" sur 2 AZ differentes — meme
quand on ne demande qu'une seule instance. C'est la regle AWS.

#### 3.4.f AWS Secrets Manager

Service de stockage de secrets (mots de passe, cles d'API). Chaque secret
peut etre lu via une API authentifiee par IAM.

**Dans ce projet** : utilise pour le password admin de la base RDS.
L'attribut `manage_master_user_password = true` sur `aws_db_instance` declenche :

1. RDS genere un password aleatoire
2. Le password est stocke dans un secret Secrets Manager auto-cree :
   `rds!db-d0435a62-...`
3. Le secret est chiffre avec une cle KMS managee AWS
4. Le password peut etre **rotate** automatiquement
5. **Le password n'apparait nulle part** : pas dans le code Terraform,
   pas dans le state, pas dans CloudTrail

**Cote ECS**, la task definition reference ce secret :

```json
"secrets": [
  { "name": "DB_PASS", "valueFrom": "arn:...:secret:...:password::" }
]
```

A chaque demarrage de tache, l'agent ECS appelle `secretsmanager:GetSecretValue`,
extrait le champ JSON `password`, et l'injecte dans la variable d'environnement
`DB_PASS` du conteneur. L'application PHP la lit comme une variable d'env normale.

**Cout** : ~$0.40/mois par secret.

#### 3.4.g Amazon ECS (Elastic Container Service) — mode Fargate

Orchestrateur de conteneurs d'AWS. Equivalent simplifie de Kubernetes,
fortement integre avec les autres services AWS.

**Modes d'execution** :

- **ECS sur EC2** : tu gere les machines, ECS y place les conteneurs
- **ECS sur Fargate** : **AWS gere les machines**, tu ne paies que les
  ressources reellement consommees (CPU + RAM × duree). C'est le mode
  serverless containers.

Ce projet utilise **Fargate** pour eviter de gerer des EC2.

**Concepts cles** :

| Element | Description |
|---------|-------------|
| **Cluster** | Groupement logique. Gratuit en soi. |
| **Task definition** | Specification immutable d'une tache : image, CPU, RAM, port, env, secrets, roles IAM, volumes, logging. |
| **Task** | Une **execution** d'une task definition. Equivalent d'un pod Kubernetes. |
| **Service** | Garantit qu'un certain nombre (`desired_count`) de taches tournent en permanence. Gere les redemarrages, les health checks, le rolling update. |
| **Revision** | Chaque modification de la task def cree une nouvelle "revision" numerotee (cd-php-app-app:1, :2, :3...). Les revisions sont immutables. |

**Le service de ce projet** : `cd-php-app-service` avec :
- `desired_count = 2` (2 taches en permanence)
- Reparties sur les 2 subnets publics (1 tache par AZ visee)
- `assign_public_ip = true` pour pouvoir pull ECR et Secrets Manager sans NAT
- Strategie de deploiement : `min healthy 50%`, `max 200%` (rolling)

#### 3.4.h Application Load Balancer (ALB)

Repartiteur de charge HTTP/HTTPS d'AWS, layer 7. Recoit le trafic depuis
internet et le distribue vers les cibles (taches ECS dans notre cas).

**Composants** :

| Element | Description |
|---------|-------------|
| **Load balancer** | Le ALB lui-meme. Possede un nom DNS public stable (`cd-php-app-alb-1905181110.us-east-1.elb.amazonaws.com`). |
| **Target group** | Liste de cibles enregistrees, avec une configuration de health check. |
| **Listener** | Regle qui ecoute sur un port (80 dans notre cas) et redirige vers un target group. |

**Health check du target group** :
- Path : `/index/login_page.php`
- Matcher : HTTP 200
- Interval : 30s
- Healthy threshold : 3 (3 succes consecutifs avant marquage healthy)
- Unhealthy threshold : 3
- Timeout : 5s

**Sticky sessions** activees (`lb_cookie`, 3600s) : un meme client est
redirige vers la meme tache ECS pendant 1 heure, pour ne pas perdre sa
session PHP (stockee localement dans `/tmp` du conteneur).

**Cout** : ~$0.0225/h + LCU charges ≈ **$16/mois en idle**.

#### 3.4.i Amazon CloudWatch Logs

Service centralise de stockage de logs. Chaque tache ECS envoie ses logs
au log group `/ecs/cd-php-app` via le driver `awslogs`.

Retention configuree a **7 jours** pour limiter les couts ($0.50/GB d'ingestion
+ $0.03/GB de stockage). Pour debug : on peut lire les logs via la console
ou en CLI :

```bash
aws logs tail /ecs/cd-php-app --follow
```

#### 3.4.j AWS IAM (Identity and Access Management)

Service de gestion des identites et permissions. **Toute action sur AWS
passe par IAM**.

**Concepts cles** :

| Element | Description |
|---------|-------------|
| **User** | Identite humaine ou applicative, avec cles d'acces long-vie. |
| **Role** | Identite **temporaire** assumable par un service AWS, une autre identite, ou un IdP externe. **Pas de cle long-vie**. |
| **Policy** | Document JSON listant `Action` autorisees sur `Resource`. |
| **Trust policy** | Document JSON qui dit "qui peut assumer ce role". |

**Les 4 IAM roles de ce projet** :

| Role | Assume par | Permet |
|------|-----------|--------|
| `cd-php-app-ecs-task-execution-role` | ECS agent (`ecs-tasks.amazonaws.com`) | Pull ECR + lire le secret RDS + ecrire CloudWatch logs |
| `cd-php-app-ecs-task-role` | Code applicatif dans le conteneur | (vide pour l'instant — extensible) |
| `cd-php-app-github-actions-deploy` | GitHub Actions via OIDC | ECR push/pull + ECS register + ECS update sur le service uniquement + iam:PassRole sur les 2 roles ECS |
| `cd-pipeline-user` (existe, mais c'est un user pas un role) | Toi en local pour `terraform apply` | Large pour simplifier — ameliorable |

#### 3.4.k AWS IAM OIDC Identity Provider

Mecanisme qui permet a **GitHub Actions** d'obtenir des credentials AWS
**sans avoir de cles long-vie** stockees comme secrets.

**Comment ca marche** :

1. GitHub possede un IdP OIDC sur `https://token.actions.githubusercontent.com`
2. On declare cet IdP dans AWS via `aws_iam_openid_connect_provider`
3. On cree un role IAM (`github-actions-deploy`) dont la trust policy autorise :
   - Le provider OIDC GitHub
   - Sous condition : `sub` matche `repo:t1impo/<repo>:ref:refs/heads/master`
     ou `repo:t1impo/<repo>:pull_request`
4. A chaque execution de workflow, GitHub produit un **JWT** signe par l'IdP,
   avec dans le payload `sub`, `aud`, `iss`, `repo`, etc.
5. L'action `configure-aws-credentials` appelle `sts:AssumeRoleWithWebIdentity`
   en passant ce JWT
6. AWS verifie la signature contre l'IdP, verifie que la trust policy passe,
   et renvoie des **credentials temporaires** valables 1h
7. Le reste du job utilise ces credentials sans le savoir

**Pourquoi c'est mieux que les cles statiques** :
- Pas de secret long-vie qui peut fuiter
- Restriction granulaire : seul **ce repo** sur **cette branche** peut assumer
- Audit complet : chaque `AssumeRoleWithWebIdentity` est trace dans CloudTrail
  avec le `sub` du JWT (on sait exactement quel workflow run a deploye quoi)

#### 3.4.l AWS STS (Security Token Service)

Service AWS qui produit des credentials temporaires. Utilise par l'echange OIDC
ci-dessus, mais aussi par tous les `assume role` cross-account, cross-service, etc.

L'audience du JWT GitHub (`aud: sts.amazonaws.com`) cible specifiquement STS.

#### 3.4.m AWS CloudTrail

(Mentionne mais pas explicitement configure ici.) Service qui trace **chaque
appel d'API AWS** dans le compte. Permet d'auditer qui a fait quoi quand.
Active par defaut sur tous les comptes AWS, 90 jours d'historique gratuit.

---

## 4. Analyse fichier par fichier

### 4.1 Workflow `.github/workflows/main.yml`

#### 4.1.1 Header et triggers (lignes 1-22)

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [ master ]
  pull_request:
    branches: [develop]

permissions:
  id-token: write    # autorise OIDC
  contents: read     # checkout du code

env:
  PHP_VERSION: '8.2'
  MYSQL_DATABASE: bd_final
  MYSQL_USER: appuser
  MYSQL_PASSWORD: apppassword
  APP_IMAGE: 023036696848.dkr.ecr.us-east-1.amazonaws.com/php-app:latest
  AWS_REGION: us-east-1
  ECR_REPOSITORY: php-app
```

**Decryptage** :

- Les variables `env.MYSQL_*` ne sont utilisees **que pour la base MySQL
  TEMPORAIRE du job test** (pas la prod). Le password de prod vient de
  Secrets Manager.
- `permissions.id-token: write` est **obligatoire** pour OIDC. Sans, l'action
  `configure-aws-credentials` ne peut pas obtenir le JWT.
- `APP_IMAGE` est plus informatif qu'utilise (la variable n'est referencee
  par aucun step).

#### 4.1.2 Job `setup` (lignes 24-64)

Prepare l'environnement PHP/Composer. Le cache Composer est cle pour la
vitesse — sans lui, chaque run reinstallerait tout depuis Packagist.

#### 4.1.3 Job `build` (lignes 65-143)

Decoupe en 4 phases :

1. **Linting** (PHP, HTML, CSS) — chaque erreur de syntaxe fait echouer le job
2. **Bootstrap check** : grep pour des fautes de frappe dans les classes
   CSS Bootstrap (cosmetique)
3. **AWS auth via OIDC**
4. **Build + push de l'image** :
   - 2 tags : SHA court (immutable, tracable) + `latest` (toujours disponible)
   - Push vers `ECR_REPOSITORY = php-app`

#### 4.1.4 Job `test` (lignes 145-296)

Le **plus complexe**. Verifie que l'image fonctionne reellement.

**1. Service container MySQL** (lignes 153-167)

```yaml
services:
  mysql:
    image: mysql:8
    ...
    options: >-
      --health-cmd="mysqladmin ping ..."
      --health-interval=5s
      --health-retries=15
```

GitHub Actions demarre automatiquement ce conteneur **avant** les steps,
attend qu'il soit healthy (max 75s), et le partage avec le job. Accessible
via `127.0.0.1:3306`.

**2. Import du schema** (lignes 195-206)

Le `bd.sql` du depot est charge dans le MySQL temporaire. C'est ce qui permet
aux tests de tourner sur une base avec un schema realiste.

**3. Login ECR + pull image** (lignes 217-226)

L'image fraichement push par le job `build` est rappellee depuis ECR pour
etre testee. **L'image testee est exactement celle qui serait deployee** —
c'est un test d'integration des plus puissants.

**4. Demarrage du conteneur applicatif** (lignes 232-247)

```bash
docker run -d --name app \
  -p 8080:80 \
  -e DB_HOST=host.docker.internal \
  -e DB_NAME=bd_final \
  -e DB_USER=appuser \
  -e DB_PASS=apppassword \
  $REGISTRY/$REPOSITORY:${TAG}
```

L'astuce `--add-host=host.docker.internal:host-gateway` permet au conteneur
de joindre le MySQL du runner par son nom DNS.

**5. Health check applicatif** (lignes 250-267)

Boucle 20 fois (max 1 minute), curl sur `/index/login_page.php`. Le test
echoue si l'application ne repond pas 200 apres 60s.

**6. PHPUnit** (lignes 281-292)

5 suites de tests sont executees. Chacune en plus de la precedente : plus
de couverture. Si une seule echoue, le job rouge.

**7. Diagnostic sur echec** (lignes 294-296)

```yaml
- name: Afficher les logs si échec
  if: failure()
  run: docker logs app || true
```

Si le job echoue, dump les logs du conteneur applicatif. Crucial pour
debugger un test rouge.

#### 4.1.5 Job `deploy` (lignes 298-341)

```yaml
deploy:
  needs: test
  if: github.event_name == 'push'
```

S'execute **seulement** si :
- Les 3 jobs precedents sont verts
- L'evenement est un `push` (pas une PR)

Steps :

1. **OIDC auth** vers AWS
2. **Calcul du SHA court** (`${GITHUB_SHA::7}`)
3. **Download de la task def courante** depuis ECS (et non d'un fichier local
   versionne — cela evite les drifts avec Terraform)
4. **Render de la task def** : modifie le champ `image` pour pointer sur
   le tag SHA fraichement push
5. **Deploy** : register + update + wait stable (10 min max)

Le **wait-for-service-stability** est ce qui rend le pipeline fiable :
GitHub Actions reste rouge tant que ECS n'a pas confirme que les nouvelles
taches sont healthy. **Si l'image est cassee, le job deploy echoue** au lieu
de basculer le trafic vers une version cassee.

### 4.2 Code Terraform

> Tous les fichiers `.tf` du dossier `infra/` sont fusionnes par Terraform :
> l'ordre des fichiers n'a pas d'importance, seules les dependances entre
> ressources comptent (resolues automatiquement).

#### 4.2.1 `backend.tf` — Stockage du state

```hcl
terraform {
  backend "s3" {
    bucket         = "tf-state-cd-php-app-023036696848"
    key            = "infra/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "terraform-locks-cd-php-app"
    encrypt        = true
  }
}
```

State stocke dans S3 (versionne, chiffre), lock concurrent gere par
DynamoDB.

#### 4.2.2 `provider.tf` — Configuration AWS

```hcl
terraform {
  required_version = ">= 1.5"
  required_providers {
    aws = { source = "hashicorp/aws", version = "~> 5.0" }
  }
}

provider "aws" {
  region = var.aws_region
  default_tags { tags = local.common_tags }
}
```

`default_tags` propage automatiquement `{Project, ManagedBy, Owner}` sur
**toutes** les ressources creees. Tres pratique pour la facturation et
l'audit.

#### 4.2.3 `variables.tf` et `locals.tf` — Parametrage

Variables exposees pour reconfiguration future :
- `aws_region` (defaut `us-east-1`)
- `project_name` (defaut `cd-php-app` — prefix de toutes les ressources)
- `vpc_cidr`, `azs`, `*_subnet_cidrs` — topologie reseau
- `db_name` — nom de la base MySQL

Locals communs :
```hcl
common_tags = {
  Project   = "cd-php-app"
  ManagedBy = "Terraform"
  Owner     = "ecole-cd-pipeline"
}
```

#### 4.2.4 `vpc.tf` — Reseau

Cree :
- 1 VPC `10.0.0.0/16`
- 1 Internet Gateway
- 2 subnets publics + 2 subnets prives (1 par AZ chaque)
- 2 route tables (publique avec route IGW, privee sans)
- 4 associations subnet -> route table

L'utilisation de `for_each` rend le code generique : changer `azs` a
`["us-east-1a","us-east-1b","us-east-1c"]` creerait 6 subnets automatiquement.

#### 4.2.5 `security.tf` — Pare-feu

3 Security Groups :

- `aws_security_group.alb` : 80/tcp depuis internet
- `aws_security_group.app` : egress complet (pull ECR/secrets/logs)
- `aws_security_group.db` : 3306/tcp depuis sg-app uniquement
- + 1 regle `aws_vpc_security_group_ingress_rule.app_from_alb` :
  80/tcp depuis sg-alb -> sg-app

**Important** : la regle separee `app_from_alb` evite un **cycle de dependances**
entre sg-app et sg-alb (chacun referencerait l'autre dans des blocs `ingress`
inlines).

#### 4.2.6 `rds.tf` — Base de donnees

```hcl
resource "aws_db_subnet_group" "main" {
  subnet_ids = [for s in aws_subnet.private : s.id]
}

resource "aws_db_instance" "main" {
  engine                       = "mysql"
  engine_version               = "8.0"
  instance_class               = "db.t3.micro"
  allocated_storage            = 20
  storage_encrypted            = true
  multi_az                     = false
  publicly_accessible          = false
  manage_master_user_password  = true   # <-- option D
  skip_final_snapshot          = true
  ...
}
```

Le password n'est jamais ecrit. AWS le genere et le met dans Secrets Manager.

#### 4.2.7 `iam.tf` — Roles ECS

Deux roles ECS, chacun assumable par `ecs-tasks.amazonaws.com` :

1. **Task execution role** :
   - Policy AWS managee `AmazonECSTaskExecutionRolePolicy` (pull ECR + logs)
   - + Policy custom **scopee sur l'ARN du secret RDS uniquement** :
     ```hcl
     actions = ["secretsmanager:GetSecretValue", "secretsmanager:DescribeSecret"]
     resources = [aws_db_instance.main.master_user_secret[0].secret_arn]
     ```

2. **Task role** : vide pour l'instant, pret a accueillir des permissions
   si l'app appelle un jour des API AWS directement.

#### 4.2.8 `ecs.tf` — Cluster + Task Definition

```hcl
resource "aws_ecs_cluster" "main" {
  name = "cd-php-app-cluster"
}

resource "aws_ecs_task_definition" "app" {
  family                   = "cd-php-app-app"
  cpu                      = "256"      # 0.25 vCPU
  memory                   = "512"      # 512 MB
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]

  container_definitions = jsonencode([{
    name  = "php-app"
    image = "${data.aws_ecr_repository.app.repository_url}:latest"

    portMappings = [{ containerPort = 80, protocol = "tcp" }]

    environment = [
      { name = "DB_HOST", value = aws_db_instance.main.address },
      { name = "DB_NAME", value = aws_db_instance.main.db_name },
      { name = "DB_USER", value = aws_db_instance.main.username }
    ]

    secrets = [{
      name      = "DB_PASS"
      valueFrom = "${aws_db_instance.main.master_user_secret[0].secret_arn}:password::"
    }]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = aws_cloudwatch_log_group.ecs_app.name
        "awslogs-region"        = "us-east-1"
        "awslogs-stream-prefix" = "ecs"
      }
    }
  }])

  lifecycle {
    ignore_changes = [container_definitions]
  }
}
```

**Point cle** : `lifecycle.ignore_changes = [container_definitions]`. La CI
cree de nouvelles revisions a chaque deploy (avec des tags d'image
differents). Sans ce `ignore`, le prochain `terraform apply` ecraserait ces
revisions en remettant l'image a `:latest`.

#### 4.2.9 `ecs_service.tf` — Service ECS

```hcl
resource "aws_ecs_service" "app" {
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = 2
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = [for s in aws_subnet.public : s.id]
    security_groups  = [aws_security_group.app.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "php-app"
    container_port   = 80
  }

  deployment_minimum_healthy_percent = 50
  deployment_maximum_percent         = 200
  health_check_grace_period_seconds  = 120

  lifecycle {
    ignore_changes = [task_definition, desired_count]
  }
}
```

`ignore_changes = [task_definition, desired_count]` est le pendant cote
service du `ignore_changes` sur la task def : laisse la CI piloter les
deploiements sans que TF ne fight.

#### 4.2.10 `alb.tf` — Load Balancer

```hcl
resource "aws_lb" "main" {
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = [for s in aws_subnet.public : s.id]
}

resource "aws_lb_target_group" "app" {
  port        = 80
  protocol    = "HTTP"
  target_type = "ip"           # obligatoire pour Fargate awsvpc

  health_check {
    path                = "/index/login_page.php"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 3
    unhealthy_threshold = 3
  }

  stickiness {
    type            = "lb_cookie"
    cookie_duration = 3600
    enabled         = true
  }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"
  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app.arn
  }
}
```

#### 4.2.11 `iam_oidc.tf` — Authentification GitHub Actions

3 ressources :

1. **OIDC provider** pour `token.actions.githubusercontent.com`
2. **Role** `cd-php-app-github-actions-deploy` avec trust policy stricte :
   ```hcl
   condition {
     test     = "StringLike"
     variable = "token.actions.githubusercontent.com:sub"
     values = [
       "repo:t1impo/fork-Systeme-de-Gestion-des-Reclamations:ref:refs/heads/master",
       "repo:t1impo/fork-Systeme-de-Gestion-des-Reclamations:pull_request"
     ]
   }
   ```
3. **Policy** avec les actions :
   - `ecr:GetAuthorizationToken` (force * — limitation AWS)
   - ECR push/pull restreint a `arn:...:repository/php-app`
   - `ecs:RegisterTaskDefinition` (force *)
   - `ecs:UpdateService`/`DescribeServices` restreint a notre service
   - `iam:PassRole` restreint aux 2 roles ECS, avec condition
     `iam:PassedToService = ecs-tasks.amazonaws.com`

C'est le **moindre privilege applique a la CI/CD**.

#### 4.2.12 `outputs.tf` — Valeurs exposees

Exposes pour usage operationnel ou par la CI :
- IDs reseau (VPC, subnets, SGs)
- Endpoint RDS (host + port + db name)
- ARN du secret RDS
- ARNs des roles IAM
- DNS public de l'ALB
- ARN du role GitHub Actions a renseigner dans le workflow

---

## 5. Securite — defense en profondeur

Le projet applique **plusieurs couches** independantes de securite. Si une
couche tombe, les autres tiennent.

### 5.1 Securite reseau (couche 1)

- **VPC isole** : ressources sur `10.0.0.0/16`, separees du reste d'internet
- **Subnets prives pour la base** : RDS sans route IGW. Meme si quelqu'un
  voulait l'attaquer depuis internet, **il n'y a aucun chemin reseau**.
- **Security Groups en chaine** : `internet -> sg-alb (80) -> sg-app (80
  depuis sg-alb) -> sg-db (3306 depuis sg-app)`. Chaque maillon n'autorise
  que le maillon directement amont.

### 5.2 Securite des secrets (couche 2)

- **Password RDS** : genere par AWS, stocke dans Secrets Manager chiffre KMS,
  jamais dans le code ni le state Terraform, lu seulement par l'agent ECS
  au demarrage de chaque tache
- **State Terraform** : bucket S3 prive avec versioning + chiffrement AES256
  + Block Public Access
- **Credentials AWS dans GitHub** : aucun secret long-vie — auth federe via
  OIDC, credentials temporaires (1h)

### 5.3 Securite des identites (couche 3)

- **Roles plutot que users** : ECS, GitHub Actions assument des roles
  temporaires
- **Trust policies strictes** : le role GitHub Actions n'est assumable que
  par **ce repo precis sur master + PR**
- **Moindre privilege sur les policies** :
  - GitHub Actions ne peut update que **notre** service, pas un autre
  - ECS task execution ne peut lire que **notre** secret RDS, pas un autre
  - `iam:PassRole` restreint aux 2 roles ECS et au service ECS uniquement

### 5.4 Securite des images (couche 4)

- **ECR privee** : pull uniquement par identite IAM autorisee
- **`scanOnPush = true`** : scan automatique CVE a chaque push d'image
- **Tags immutables logiquement** : chaque deploiement utilise le tag SHA
  du commit (jamais reutilise), pas juste `:latest` (qui pourrait etre
  ecrase)
- **Storage encrypted** : AES256 cote ECR

### 5.5 Securite des donnees au repos (couche 5)

- **RDS** : `storage_encrypted = true` (chiffrement disque AWS-managed)
- **S3 state** : SSE-S3 (AES256 server-side encryption)
- **Secrets Manager** : chiffrement KMS AWS-managed
- **CloudWatch Logs** : chiffrement par defaut

---

## 6. Haute disponibilite

### 6.1 Decomposition du SLA

| Element | Strategie HA | Limite actuelle |
|---------|--------------|-----------------|
| App PHP (ECS) | 2 taches Fargate sur 2 AZ | OK, vraie HA |
| Trafic entrant (ALB) | ALB managee AWS, redondant cross-AZ | OK, gere par AWS |
| Base de donnees (RDS) | Single-AZ (multi_az = false) | **Point unique de panne** : si l'AZ tombe, RDS aussi. Acceptable pour projet d'ecole. |
| Image (ECR) | Replication intra-region | OK, gere par AWS |
| Secrets (Secrets Manager) | Replication intra-region | OK, gere par AWS |

### 6.2 Le rolling deployment

Lors d'un deploy, ECS applique un **rolling update** :

```
Etat initial :   [v1 task A]  [v1 task B]   ← desired=2, healthy=2
                       │             │
Phase 1 :        [v1 task A]  [v1 task B]  [v2 task C]  [v2 task D]
                       │             │             │             │
                                                   (en cours de demarrage,
                                                    pas encore healthy au TG)
                                                   max 200% → max 4 taches
                       │             │             │             │
Phase 2 :        [v1 task A]  [v1 task B]  [v2 task C ✓]  [v2 task D ✓]
                                                   (ALB declare healthy
                                                    apres 3 checks OK)
                       │
Phase 3 :   ALB retire les anciennes du target group
            (deregistration_delay = 30s pour drainer les connexions)
                       │
Etat final :     [v2 task C]  [v2 task D]   ← desired=2, healthy=2
```

`deployment_minimum_healthy_percent = 50` garantit qu'**au moins 1 tache
saine** est disponible a tout moment. Aucun utilisateur ne subit de 5xx
pendant le deploiement.

### 6.3 Les health checks

Deux niveaux de health check :

1. **Health check ECS** (de la tache elle-meme) : par defaut le container exit
   code suffit. Une tache qui crash est redemarree.
2. **Health check ALB** (du target group) : GET `/index/login_page.php` toutes
   les 30s. Une tache non-healthy est retiree du load balancing (le trafic
   ne lui est plus envoye) sans pour autant etre tuee, pour permettre
   inspection.

Sticky sessions (cookie ALB) compensent l'absence de session store partage
entre taches.

---

## 7. Couts et strategie d'economie

### 7.1 Couts mensuels estimes (sans Free Tier)

| Service | Cout/mois |
|---------|-----------|
| ECS Fargate (2 × 0.25 vCPU + 0.5 GB) | ~$18 |
| ALB | ~$16 |
| RDS db.t3.micro 20GB | ~$13 (hors Free Tier 1ere annee) |
| Secrets Manager | ~$0.40 |
| ECR (image ~190 MB) | $0 (Free Tier perpetuel sous 500 MB) |
| S3 (state, ~50 KB) | $0 |
| DynamoDB (lock) | $0 |
| CloudWatch Logs (retention 7 jours, faible volume) | ~$0.20 |
| Data transfer out | variable |
| **Total estime** | **~$48/mois** si tout tourne 24/7 |

### 7.2 Avec Free Tier (1ere annee)

| Service | Free Tier | Reste a payer |
|---------|-----------|---------------|
| RDS db.t3.micro | 750h/mois 12 mois | $0 |
| RDS 20GB gp2 | gratuit | $0 |
| ECS Fargate | aucun Free Tier | ~$18 |
| ALB | aucun Free Tier | ~$16 |

**Cout reel premiere annee** : ~$35/mois si tout tourne 24/7.

### 7.3 Strategies d'economie

**Approche A — Detruire apres chaque session de tests**

```bash
cd infra/
terraform destroy -auto-approve
```

Detruit RDS, ECS Service, ALB, etc. Garde le state, le bucket S3, l'ECR
(ces ressources sont gratuites ou hors Terraform).

Recreation : `terraform apply` (~10 min).

**Cout d'une session de 1h** : ~$0.05.

**Approche B — Reduire le desired_count a 0 + supprimer l'ALB**

```bash
aws ecs update-service --cluster ... --service ... --desired-count 0
```

Garde la config, arrete les conteneurs. Mais l'ALB continue de payer.
Pour vraiment economiser il faut detruire l'ALB aussi.

### 7.4 Avec les $100 de credit AWS

A ~$1.20/jour si tout tourne 24/7, **les credits durent ~83 jours** —
largement assez pour un projet d'ecole.

---

## 8. Choix d'architecture et trade-offs assumes

> Cette section sert a **defendre les choix** lors d'une presentation orale.

### 8.1 ECS Fargate plutot que Kubernetes

| Critere | Fargate | Kubernetes (EKS) |
|---------|---------|------------------|
| Courbe d'apprentissage | Faible | Eleve |
| Cout fixe (control plane) | $0 | $73/mois |
| Auto-scaling | OK | OK + plus avance |
| Portabilite (multi-cloud) | Faible (AWS-specifique) | Forte |

**Pour un projet d'ecole** : Fargate est nettement plus adapte. Le pipeline
demontre la maitrise du concept "container orchestration" sans noyer dans
la complexite Kubernetes.

### 8.2 Subnets publics + IP publique plutot que NAT Gateway

| Approche | Cout | Securite |
|----------|------|----------|
| **Choisi** : Public subnets + assign_public_ip | $0 | OK si SG strict |
| Private subnets + NAT Gateway | ~$32/mois ($0.045/h × 2 AZ + data) | Meilleure |

**Trade-off assume** : on accepte que les taches aient une IP publique
parce que le **Security Group `sg-app`** n'autorise que l'ingress depuis
le `sg-alb`. Resultat reseau **fonctionnellement equivalent** a un setup
prive + NAT, sans le cout. **A documenter dans le rapport**.

### 8.3 RDS single-AZ plutot que multi-AZ

| Approche | Cout | RTO en cas de panne AZ |
|----------|------|----------------------|
| **Choisi** : Single-AZ | $13/mois | 10+ min (recreation manuelle) |
| Multi-AZ | $26/mois (×2) | < 60s (failover auto) |

**Trade-off assume** : projet d'ecole, on accepte le risque AZ.

### 8.4 Password DB via `manage_master_user_password` plutot que random_password TF

| Approche | Avantages | Inconvenients |
|----------|-----------|---------------|
| **Choisi** : managed | Rotation automatique, jamais dans le state TF | ~$0.40/mois |
| `random_password` TF | Gratuit | Password dans le state (chiffre mais lisible) |
| SSM Parameter Store | Gratuit | Plus de code TF a ecrire |

**Trade-off assume** : on paye $0.40/mois pour la garantie absolue que le
password n'est **jamais** en clair dans aucun fichier.

### 8.5 OIDC plutot que cles AWS statiques

| Approche | Surface d'attaque |
|----------|------------------|
| **Choisi** : OIDC | Credentials temporaires 1h, restreints au repo/branche |
| Secrets statiques | Si fuite : compromission permanente |

**Pas de trade-off** : OIDC est strictement superieur. La seule raison de
ne pas l'utiliser serait des contraintes anciennes ou un cloud non supporte.

### 8.6 ALB + sticky sessions plutot que Redis pour les sessions PHP

| Approche | Cout | Vraie HA ? |
|----------|------|-----------|
| **Choisi** : sticky sessions | $0 | Non (la session est sur 1 tache) |
| ElastiCache Redis | ~$12/mois | Oui (sessions partagees) |

**Trade-off assume** : sticky suffit pour le scope du projet. Une vraie
architecture de prod aurait un session store externe (Redis, DynamoDB, ou
base de donnees).

### 8.7 Policies `*FullAccess` sur l'utilisateur de developpement

L'utilisateur IAM `cd-pipeline-user` (utilise localement pour
`terraform apply`) a `AmazonEC2ContainerRegistryFullAccess`, `AmazonECSFullAccess`,
etc. C'est large.

**Trade-off assume** : simplifie le developpement local. Pour la production,
on creerait une policy custom liste blanche. Dans un vrai cycle de vie,
seul un pipeline CI applique l'infrastructure, jamais un humain.

---

## 9. Glossaire

| Terme | Definition |
|-------|-----------|
| **AZ** (Availability Zone) | Zone de disponibilite AWS — un data center independant (alimentation, reseau, refroidissement) dans une region. La region `us-east-1` a 6 AZs. |
| **CD** | Continuous Deployment : mise en production automatique sans intervention manuelle. |
| **CI** | Continuous Integration : verification automatique de chaque commit (lint, build, tests). |
| **CIDR** | Notation pour designer une plage d'adresses IP (`10.0.0.0/16` = 65 536 adresses). |
| **CVE** | Common Vulnerabilities and Exposures : identifiant standardise d'une faille de securite. |
| **ECS** | Elastic Container Service : orchestrateur de conteneurs AWS. |
| **ECR** | Elastic Container Registry : registre Docker prive AWS. |
| **ENI** | Elastic Network Interface : interface reseau virtuelle attachee a une ressource AWS. |
| **Fargate** | Mode d'execution serverless pour ECS — AWS gere les machines. |
| **HA** (High Availability) | Architecture conque pour tolerer la panne d'un composant sans interruption. |
| **HCL** | HashiCorp Configuration Language — langage de Terraform. |
| **IaC** (Infrastructure as Code) | Pratique de declarer l'infrastructure dans du code versionnable. Terraform en est l'outil de reference. |
| **IAM** | Identity and Access Management — service AWS de gestion des permissions. |
| **IdP** | Identity Provider — fournisseur d'identite (Google, GitHub, etc.). |
| **JWT** | JSON Web Token — token signe utilise par OIDC. |
| **KMS** | Key Management Service — service AWS de gestion de cles de chiffrement. |
| **LCU** | Load Balancer Capacity Unit — unite de facturation des ALB. |
| **OIDC** | OpenID Connect — protocole d'authentification federe au-dessus d'OAuth 2.0. |
| **RDS** | Relational Database Service — bases de donnees managees AWS. |
| **SG** (Security Group) | Pare-feu statefull au niveau ENI. |
| **STS** | Security Token Service — service AWS de credentials temporaires. |
| **Task definition** | Specification d'une tache ECS (image, ressources, env, secrets). |
| **Target group** | Liste de cibles enregistrees auprès d'un ALB, avec un health check. |
| **VPC** | Virtual Private Cloud — reseau prive isole dans AWS. |

---

## Annexe — Comment reproduire ce pipeline ?

### Prerequis

- Compte AWS avec credit ou Free Tier eligible
- Compte GitHub
- Git, AWS CLI, Terraform sur la machine de developpement

### Etapes resumees

1. **Bootstrap state backend** (AWS CLI, une fois) :
   - Bucket S3 `tf-state-...` avec versioning + encryption + block public access
   - Table DynamoDB `terraform-locks-...`

2. **Creer le depot ECR** (AWS CLI) :
   ```bash
   aws ecr create-repository --repository-name php-app \
     --image-scanning-configuration scanOnPush=true
   ```

3. **Appliquer le code Terraform** :
   ```bash
   cd infra/
   terraform init
   terraform plan
   terraform apply
   ```

4. **Configurer le workflow GitHub Actions** :
   - Le role ARN exporte par `terraform output github_actions_role_arn`
     doit etre reporte dans le YAML.
   - Activer `permissions: id-token: write` au niveau workflow.

5. **Premier push sur master** : le pipeline s'execute, deploie sur ECS.

6. **Verification** : `curl http://<ALB-DNS>/index/login_page.php` → 200.

### Pour detruire l'infra (economie)

```bash
cd infra/
terraform destroy -auto-approve
```

Ne detruit pas : le bucket S3 du state, la table DynamoDB de lock, le depot
ECR — ces ressources sont gerees hors Terraform et survivent (gratuites).

---

*Document genere a partir des fichiers du depot
`fork-Systeme-de-Gestion-des-Reclamations`. Toute modification du
pipeline doit etre repercutee ici pour rester pedagogique.*
