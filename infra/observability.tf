# =============================================================================
# Couche d'observabilite (sous-etape 3.5)
#   - Dashboard CloudWatch consolide
#   - 2 alarmes (ALB 5xx, ECS CPU > 80%)
# Container Insights est active dans ecs.tf (containerInsights = "enabled")
# =============================================================================

# Locals : suffixes ARN necessaires pour les dimensions CloudWatch ALB/TG
locals {
  alb_arn_suffix = aws_lb.main.arn_suffix              # ex: "app/cd-php-app-alb/abc..."
  tg_arn_suffix  = aws_lb_target_group.app.arn_suffix
}

# =============================================================================
# Dashboard : cd-php-app-overview
# Layout 24 colonnes :
#   Ligne 1 (y=0 ) : ECS CPU | ECS Mem | ECS Tasks         (3 x 8)
#   Ligne 2 (y=6 ) : ALB Req | Latence | Hosts | 5xx       (4 x 6)
#   Ligne 3 (y=12) : RDS CPU | RDS Connections             (2 x 12)
# =============================================================================
resource "aws_cloudwatch_dashboard" "main" {
  dashboard_name = "${var.project_name}-overview"

  dashboard_body = jsonencode({
    widgets = [
      # ---------- Ligne 1 : ECS ----------
      {
        type   = "metric"
        x      = 0
        y      = 0
        width  = 8
        height = 6
        properties = {
          title   = "ECS - CPU (%)"
          region  = var.aws_region
          period  = 60
          stat    = "Average"
          view    = "timeSeries"
          stacked = false
          metrics = [
            ["AWS/ECS", "CPUUtilization",
              "ClusterName", aws_ecs_cluster.main.name,
              "ServiceName", aws_ecs_service.app.name]
          ]
          yAxis = { left = { min = 0, max = 100 } }
        }
      },
      {
        type   = "metric"
        x      = 8
        y      = 0
        width  = 8
        height = 6
        properties = {
          title   = "ECS - Memoire (%)"
          region  = var.aws_region
          period  = 60
          stat    = "Average"
          view    = "timeSeries"
          stacked = false
          metrics = [
            ["AWS/ECS", "MemoryUtilization",
              "ClusterName", aws_ecs_cluster.main.name,
              "ServiceName", aws_ecs_service.app.name]
          ]
          yAxis = { left = { min = 0, max = 100 } }
        }
      },
      {
        type   = "metric"
        x      = 16
        y      = 0
        width  = 8
        height = 6
        properties = {
          title   = "ECS - Taches en cours (desired = 2)"
          region  = var.aws_region
          period  = 60
          stat    = "Average"
          view    = "timeSeries"
          stacked = false
          metrics = [
            ["ECS/ContainerInsights", "RunningTaskCount",
              "ClusterName", aws_ecs_cluster.main.name,
              "ServiceName", aws_ecs_service.app.name]
          ]
          yAxis = { left = { min = 0 } }
          annotations = {
            horizontal = [
              { value = 2, label = "desired_count", color = "#2ca02c" }
            ]
          }
        }
      },

      # ---------- Ligne 2 : ALB ----------
      {
        type   = "metric"
        x      = 0
        y      = 6
        width  = 6
        height = 6
        properties = {
          title  = "ALB - Requetes (sum/min)"
          region = var.aws_region
          period = 60
          stat   = "Sum"
          view   = "timeSeries"
          metrics = [
            ["AWS/ApplicationELB", "RequestCount",
              "LoadBalancer", local.alb_arn_suffix]
          ]
          yAxis = { left = { min = 0 } }
        }
      },
      {
        type   = "metric"
        x      = 6
        y      = 6
        width  = 6
        height = 6
        properties = {
          title  = "ALB - Latence cible (s)"
          region = var.aws_region
          period = 60
          stat   = "Average"
          view   = "timeSeries"
          metrics = [
            ["AWS/ApplicationELB", "TargetResponseTime",
              "LoadBalancer", local.alb_arn_suffix,
              "TargetGroup", local.tg_arn_suffix]
          ]
          yAxis = { left = { min = 0 } }
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 6
        width  = 6
        height = 6
        properties = {
          title   = "ALB - Hotes sains / malades"
          region  = var.aws_region
          period  = 60
          stat    = "Average"
          view    = "timeSeries"
          stacked = false
          metrics = [
            ["AWS/ApplicationELB", "HealthyHostCount",
              "LoadBalancer", local.alb_arn_suffix,
              "TargetGroup", local.tg_arn_suffix],
            ["AWS/ApplicationELB", "UnHealthyHostCount",
              "LoadBalancer", local.alb_arn_suffix,
              "TargetGroup", local.tg_arn_suffix]
          ]
          yAxis = { left = { min = 0 } }
        }
      },
      {
        type   = "metric"
        x      = 18
        y      = 6
        width  = 6
        height = 6
        properties = {
          title  = "ALB - Erreurs 5xx (cumul/min)"
          region = var.aws_region
          period = 60
          stat   = "Sum"
          view   = "timeSeries"
          metrics = [
            ["AWS/ApplicationELB", "HTTPCode_Target_5XX_Count",
              "LoadBalancer", local.alb_arn_suffix],
            ["AWS/ApplicationELB", "HTTPCode_ELB_5XX_Count",
              "LoadBalancer", local.alb_arn_suffix]
          ]
          yAxis = { left = { min = 0 } }
        }
      },

      # ---------- Ligne 3 : RDS ----------
      {
        type   = "metric"
        x      = 0
        y      = 12
        width  = 12
        height = 6
        properties = {
          title  = "RDS - CPU (%)"
          region = var.aws_region
          period = 60
          stat   = "Average"
          view   = "timeSeries"
          metrics = [
            ["AWS/RDS", "CPUUtilization",
              "DBInstanceIdentifier", aws_db_instance.main.id]
          ]
          yAxis = { left = { min = 0, max = 100 } }
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 12
        width  = 12
        height = 6
        properties = {
          title  = "RDS - Connexions actives"
          region = var.aws_region
          period = 60
          stat   = "Average"
          view   = "timeSeries"
          metrics = [
            ["AWS/RDS", "DatabaseConnections",
              "DBInstanceIdentifier", aws_db_instance.main.id]
          ]
          yAxis = { left = { min = 0 } }
        }
      }
    ]
  })
}

# =============================================================================
# Alarme 1 : ALB renvoie des 5xx (target ou ELB)
#   Trigger : >= 5 erreurs 5xx cumulees sur 5 minutes
# =============================================================================
resource "aws_cloudwatch_metric_alarm" "alb_5xx" {
  alarm_name          = "${var.project_name}-alb-5xx"
  alarm_description   = "ALB renvoie des erreurs 5xx (cumul >= 5 sur 5min)"
  comparison_operator = "GreaterThanOrEqualToThreshold"
  evaluation_periods  = 1
  threshold           = 5
  treat_missing_data  = "notBreaching"

  metric_query {
    id          = "total_5xx"
    label       = "5xx (target + ELB)"
    return_data = true
    expression  = "target_5xx + elb_5xx"
  }

  metric_query {
    id = "target_5xx"
    metric {
      namespace   = "AWS/ApplicationELB"
      metric_name = "HTTPCode_Target_5XX_Count"
      period      = 300
      stat        = "Sum"
      dimensions = {
        LoadBalancer = local.alb_arn_suffix
      }
    }
  }

  metric_query {
    id = "elb_5xx"
    metric {
      namespace   = "AWS/ApplicationELB"
      metric_name = "HTTPCode_ELB_5XX_Count"
      period      = 300
      stat        = "Sum"
      dimensions = {
        LoadBalancer = local.alb_arn_suffix
      }
    }
  }

  tags = {
    Name = "${var.project_name}-alb-5xx"
  }
}

# =============================================================================
# Alarme 2 : CPU des taches ECS au-dessus de 80%
#   Trigger : Average > 80% sur 2 periodes consecutives de 5 min (= 10 min)
# =============================================================================
resource "aws_cloudwatch_metric_alarm" "ecs_cpu_high" {
  alarm_name          = "${var.project_name}-ecs-cpu-high"
  alarm_description   = "CPU service ECS > 80% pendant 10 min"
  namespace           = "AWS/ECS"
  metric_name         = "CPUUtilization"
  dimensions = {
    ClusterName = aws_ecs_cluster.main.name
    ServiceName = aws_ecs_service.app.name
  }
  statistic           = "Average"
  period              = 300
  evaluation_periods  = 2
  threshold           = 80
  comparison_operator = "GreaterThanThreshold"
  treat_missing_data  = "notBreaching"

  tags = {
    Name = "${var.project_name}-ecs-cpu-high"
  }
}
