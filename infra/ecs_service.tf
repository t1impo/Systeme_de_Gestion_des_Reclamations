# =============================================================================
# ECS Service : 2 taches Fargate HA sur 2 AZ
# Subnets publics + IP publique pour eviter NAT Gateway
# Trafic entrant filtre par sg-app (uniquement depuis sg-alb)
# =============================================================================
resource "aws_ecs_service" "app" {
  name            = "${var.project_name}-service"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = 2

  launch_type = "FARGATE"

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

  # Rolling update : 50% min healthy, 200% max = remplace 2 par 2
  deployment_minimum_healthy_percent = 50
  deployment_maximum_percent         = 200

  # Grace period le temps que l'ALB declare healthy
  health_check_grace_period_seconds = 120

  # La CI gere les updates de task_definition via amazon-ecs-deploy-task-definition.
  # On ignore aussi desired_count pour permettre un scaling manuel sans drift TF.
  lifecycle {
    ignore_changes = [task_definition, desired_count]
  }

  depends_on = [aws_lb_listener.http]

  tags = {
    Name = "${var.project_name}-service"
  }
}
