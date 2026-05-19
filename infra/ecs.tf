# =============================================================================
# CloudWatch Log Group pour les logs des taches ECS
# =============================================================================
resource "aws_cloudwatch_log_group" "ecs_app" {
  name              = "/ecs/${var.project_name}"
  retention_in_days = 7

  tags = {
    Name = "${var.project_name}-logs"
  }
}

# =============================================================================
# ECS Cluster (gratuit, juste un groupement logique)
# =============================================================================
resource "aws_ecs_cluster" "main" {
  name = "${var.project_name}-cluster"

  setting {
    name  = "containerInsights"
    value = "disabled"
  }

  tags = {
    Name = "${var.project_name}-cluster"
  }
}

resource "aws_ecs_cluster_capacity_providers" "main" {
  cluster_name       = aws_ecs_cluster.main.name
  capacity_providers = ["FARGATE"]

  default_capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 1
  }
}

# =============================================================================
# Data source : le depot ECR a ete cree en Etape 1.3 via AWS CLI (hors TF)
# =============================================================================
data "aws_ecr_repository" "app" {
  name = "php-app"
}

# =============================================================================
# Task Definition initiale (la CI prendra le relais via amazon-ecs-deploy-task-definition)
# =============================================================================
resource "aws_ecs_task_definition" "app" {
  family                   = "${var.project_name}-app"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "256"
  memory                   = "512"

  execution_role_arn = aws_iam_role.ecs_task_execution.arn
  task_role_arn      = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name      = "php-app"
      image     = "${data.aws_ecr_repository.app.repository_url}:latest"
      essential = true

      portMappings = [
        {
          containerPort = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_NAME", value = aws_db_instance.main.db_name },
        { name = "DB_USER", value = aws_db_instance.main.username }
      ]

      secrets = [
        {
          name      = "DB_PASS"
          valueFrom = "${aws_db_instance.main.master_user_secret[0].secret_arn}:password::"
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.ecs_app.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "ecs"
        }
      }
    }
  ])

  lifecycle {
    # La CI cree de nouvelles revisions a chaque deploy. TF ignore les changements
    # de container_definitions pour ne pas ecraser ce que fait la CI.
    ignore_changes = [container_definitions]
  }

  tags = {
    Name = "${var.project_name}-app"
  }
}
