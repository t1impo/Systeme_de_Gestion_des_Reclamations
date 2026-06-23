# =============================================================================
# VPC outputs (sous-etape 2.3)
# =============================================================================
output "vpc_id" {
  value       = aws_vpc.main.id
  description = "ID du VPC"
}

output "public_subnet_ids" {
  value       = [for s in aws_subnet.public : s.id]
  description = "IDs des subnets publics (pour ALB et eventuellement ECS public)"
}

output "private_subnet_ids" {
  value       = [for s in aws_subnet.private : s.id]
  description = "IDs des subnets prives (pour RDS et eventuellement ECS prive)"
}

# =============================================================================
# Security Groups outputs (sous-etape 2.4)
# =============================================================================
output "app_security_group_id" {
  value       = aws_security_group.app.id
  description = "ID du SG application (ECS)"
}

output "db_security_group_id" {
  value       = aws_security_group.db.id
  description = "ID du SG base RDS"
}

# =============================================================================
# RDS outputs (sous-etape 2.5)
# =============================================================================
output "rds_endpoint" {
  value       = aws_db_instance.main.endpoint
  description = "Endpoint de connexion MySQL (host:port)"
}

output "rds_port" {
  value       = aws_db_instance.main.port
  description = "Port MySQL"
}

output "rds_db_name" {
  value       = aws_db_instance.main.db_name
  description = "Nom de la base"
}

output "rds_master_user_secret_arn" {
  value       = aws_db_instance.main.master_user_secret[0].secret_arn
  description = "ARN du secret AWS Secrets Manager contenant le password admin"
  sensitive   = true
}

# =============================================================================
# IAM outputs (sous-etape 3.1)
# =============================================================================
output "ecs_task_execution_role_arn" {
  value       = aws_iam_role.ecs_task_execution.arn
  description = "ARN du task execution role (pour task definition)"
}

output "ecs_task_role_arn" {
  value       = aws_iam_role.ecs_task.arn
  description = "ARN du task role (pour task definition)"
}

# =============================================================================
# ECS outputs (sous-etapes 3.2 + 3.3 + 3.4)
# =============================================================================
output "ecs_cluster_name" {
  value       = aws_ecs_cluster.main.name
  description = "Nom du cluster ECS (utilise par la CI)"
}

output "ecs_task_definition_family" {
  value       = aws_ecs_task_definition.app.family
  description = "Family de la task definition (utilise par la CI)"
}

output "ecs_log_group_name" {
  value       = aws_cloudwatch_log_group.ecs_app.name
  description = "Nom du log group CloudWatch"
}

output "ecr_repository_url" {
  value       = data.aws_ecr_repository.app.repository_url
  description = "URI du depot ECR (utilise par la CI)"
}

# =============================================================================
# ALB outputs (sous-etape 3.5)
# =============================================================================
output "alb_dns_name" {
  value       = aws_lb.main.dns_name
  description = "URL publique de l'ALB (test final)"
}

output "alb_security_group_id" {
  value       = aws_security_group.alb.id
  description = "ID du SG ALB (utilise par sg-app en 3.6)"
}

output "target_group_arn" {
  value       = aws_lb_target_group.app.arn
  description = "ARN du target group (utilise par le service ECS en 3.7)"
}

# =============================================================================
# ECS Service output (sous-etape 3.7)
# =============================================================================
output "ecs_service_name" {
  value       = aws_ecs_service.app.name
  description = "Nom du service ECS (utilise par la CI pour update-service)"
}

# =============================================================================
# OIDC output (sous-etape 3.8)
# =============================================================================
output "github_actions_role_arn" {
  value       = aws_iam_role.github_actions_deploy.arn
  description = "ARN du role assume par GitHub Actions via OIDC (a renseigner dans le workflow)"
}

# =============================================================================
# Observabilite outputs (sous-etape 3.5)
# =============================================================================
output "dashboard_url" {
  value       = "https://console.aws.amazon.com/cloudwatch/home?region=${var.aws_region}#dashboards:name=${aws_cloudwatch_dashboard.main.dashboard_name}"
  description = "URL directe vers le dashboard CloudWatch consolide"
}
