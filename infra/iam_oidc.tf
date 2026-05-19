# =============================================================================
# OIDC Identity Provider pour GitHub Actions
#   Permet aux workflows GitHub Actions d'assumer un role IAM sans cle long-vie
# =============================================================================
resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]

  # Thumbprints des certificats GitHub (defense en profondeur, AWS verifie aussi
  # le certificat lui-meme quand l'URL correspond a GitHub)
  thumbprint_list = [
    "6938fd4d98bab03faadb97b34396831e3780aea1",
    "1c58a3a8518e8759bf075b76b750d4f2df264fcd"
  ]

  tags = {
    Name = "github-actions-oidc"
  }
}

# =============================================================================
# Trust policy : restreint le role au repo t1impo/fork-... sur master + PR
# =============================================================================
data "aws_iam_policy_document" "github_actions_trust" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    condition {
      test     = "StringLike"
      variable = "token.actions.githubusercontent.com:sub"
      values = [
        "repo:t1impo/fork-Systeme-de-Gestion-des-Reclamations:ref:refs/heads/master",
        "repo:t1impo/fork-Systeme-de-Gestion-des-Reclamations:pull_request"
      ]
    }
  }
}

# =============================================================================
# Role assumable par GitHub Actions
# =============================================================================
resource "aws_iam_role" "github_actions_deploy" {
  name               = "${var.project_name}-github-actions-deploy"
  description        = "Role assumed by GitHub Actions via OIDC for CD pipeline"
  assume_role_policy = data.aws_iam_policy_document.github_actions_trust.json
}

# =============================================================================
# Policy : permissions minimales pour build + test + deploy
# =============================================================================
data "aws_iam_policy_document" "github_actions_deploy" {
  # ECR : token global (limitation AWS, ne peut pas etre scope)
  statement {
    sid       = "ECRAuthToken"
    effect    = "Allow"
    actions   = ["ecr:GetAuthorizationToken"]
    resources = ["*"]
  }

  # ECR : push/pull sur le depot php-app uniquement
  statement {
    sid    = "ECRPushPull"
    effect = "Allow"
    actions = [
      "ecr:BatchCheckLayerAvailability",
      "ecr:BatchGetImage",
      "ecr:GetDownloadUrlForLayer",
      "ecr:InitiateLayerUpload",
      "ecr:UploadLayerPart",
      "ecr:CompleteLayerUpload",
      "ecr:PutImage",
      "ecr:DescribeRepositories",
      "ecr:DescribeImages",
      "ecr:ListImages"
    ]
    resources = [data.aws_ecr_repository.app.arn]
  }

  # ECS : register task def (API globale, scope * obligatoire)
  statement {
    sid       = "ECSRegisterTaskDef"
    effect    = "Allow"
    actions   = ["ecs:RegisterTaskDefinition", "ecs:DescribeTaskDefinition"]
    resources = ["*"]
  }

  # ECS : update/describe uniquement sur NOTRE service
  statement {
    sid    = "ECSUpdateService"
    effect = "Allow"
    actions = [
      "ecs:UpdateService",
      "ecs:DescribeServices"
    ]
    resources = [aws_ecs_service.app.id]
  }

  # iam:PassRole : uniquement les 2 roles ECS et uniquement vers ecs-tasks
  statement {
    sid     = "PassRoleToECS"
    effect  = "Allow"
    actions = ["iam:PassRole"]
    resources = [
      aws_iam_role.ecs_task_execution.arn,
      aws_iam_role.ecs_task.arn
    ]
    condition {
      test     = "StringEquals"
      variable = "iam:PassedToService"
      values   = ["ecs-tasks.amazonaws.com"]
    }
  }
}

resource "aws_iam_policy" "github_actions_deploy" {
  name        = "${var.project_name}-github-actions-deploy"
  description = "Permissions CD GitHub Actions (moindre privilege)"
  policy      = data.aws_iam_policy_document.github_actions_deploy.json
}

resource "aws_iam_role_policy_attachment" "github_actions_deploy" {
  role       = aws_iam_role.github_actions_deploy.name
  policy_arn = aws_iam_policy.github_actions_deploy.arn
}
