# =============================================================================
# Trust policy commune aux 2 roles ECS
# =============================================================================
data "aws_iam_policy_document" "ecs_tasks_assume_role" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# =============================================================================
# Task EXECUTION role
#   Utilise par l'agent ECS (pas par le code de l'app)
#   Permissions: pull ECR, fetch secrets, push CloudWatch logs
# =============================================================================
resource "aws_iam_role" "ecs_task_execution" {
  name               = "${var.project_name}-ecs-task-execution-role"
  assume_role_policy = data.aws_iam_policy_document.ecs_tasks_assume_role.json
}

# Policy managee AWS : permet pull ECR + write CloudWatch logs
resource "aws_iam_role_policy_attachment" "ecs_task_execution_managed" {
  role       = aws_iam_role.ecs_task_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# Policy custom : lecture du secret RDS, restreinte a CE secret uniquement
data "aws_iam_policy_document" "ecs_read_db_secret" {
  statement {
    effect = "Allow"
    actions = [
      "secretsmanager:GetSecretValue",
      "secretsmanager:DescribeSecret",
    ]
    resources = [aws_db_instance.main.master_user_secret[0].secret_arn]
  }
}

resource "aws_iam_policy" "ecs_read_db_secret" {
  name        = "${var.project_name}-ecs-read-db-secret"
  description = "Permet a l'agent ECS de lire le secret RDS"
  policy      = data.aws_iam_policy_document.ecs_read_db_secret.json
}

resource "aws_iam_role_policy_attachment" "ecs_task_execution_db_secret" {
  role       = aws_iam_role.ecs_task_execution.name
  policy_arn = aws_iam_policy.ecs_read_db_secret.arn
}

# =============================================================================
# Task role : utilise par le code de l'app PHP a l'execution
#   Vide pour l'instant — l'app ne fait pas d'appels AWS directs
# =============================================================================
resource "aws_iam_role" "ecs_task" {
  name               = "${var.project_name}-ecs-task-role"
  assume_role_policy = data.aws_iam_policy_document.ecs_tasks_assume_role.json
}
