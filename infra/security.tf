# =============================================================================
# Security Group : application (futur ECS Fargate)
# Pas d'ingress declare ici — l'ingress depuis le SG ALB sera ajoute en Etape 3
# Egress: tout autorise (ECS doit pull ECR, parler a RDS, logger CloudWatch)
# =============================================================================
resource "aws_security_group" "app" {
  name        = "${var.project_name}-app-sg"
  description = "Security group pour les taches ECS applicatives"
  vpc_id      = aws_vpc.main.id

  egress {
    description = "Autoriser tout trafic sortant (pull ECR, RDS, CloudWatch)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project_name}-app-sg"
  }
}

# =============================================================================
# Security Group : base de donnees RDS MySQL
# Moindre privilege : ingress 3306 UNIQUEMENT depuis le SG application
# Egress laisse par defaut (all-egress) — RDS n'initie aucune connexion sortante
# =============================================================================
resource "aws_security_group" "db" {
  name        = "${var.project_name}-db-sg"
  description = "Security group pour la base RDS MySQL"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "MySQL 3306 depuis les taches ECS uniquement"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }

  tags = {
    Name = "${var.project_name}-db-sg"
  }
}

# =============================================================================
# Ingress sg-app depuis sg-alb (sous-etape 3.6)
# Les taches ECS n'acceptent du trafic HTTP QUE depuis l'ALB, jamais depuis internet
# meme si elles ont une IP publique pour pull ECR / Secrets Manager
# =============================================================================
resource "aws_vpc_security_group_ingress_rule" "app_from_alb" {
  security_group_id            = aws_security_group.app.id
  referenced_security_group_id = aws_security_group.alb.id
  from_port                    = 80
  to_port                      = 80
  ip_protocol                  = "tcp"
  description                  = "HTTP depuis ALB uniquement"

  tags = {
    Name = "${var.project_name}-app-from-alb"
  }
}
