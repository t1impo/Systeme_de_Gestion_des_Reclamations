# =============================================================================
# DB subnet group : indique a RDS dans quels subnets placer l'instance
# Exigence RDS : minimum 2 subnets dans 2 AZ differentes (meme en single-AZ)
# On utilise les subnets PRIVES (pas d'acces internet, RDS n'en a pas besoin)
# =============================================================================
resource "aws_db_subnet_group" "main" {
  name        = "${var.project_name}-db-subnets"
  description = "Subnet group RDS MySQL (subnets prives)"
  subnet_ids  = [for s in aws_subnet.private : s.id]

  tags = {
    Name = "${var.project_name}-db-subnets"
  }
}

# =============================================================================
# Instance RDS MySQL
#   - single-AZ (multi_az = false)
#   - db.t3.micro / 20 GB (Free Tier eligible)
#   - private (publicly_accessible = false)
#   - master password gere par AWS Secrets Manager (manage_master_user_password)
# =============================================================================
resource "aws_db_instance" "main" {
  identifier        = "${var.project_name}-mysql"
  engine            = "mysql"
  engine_version    = "8.0"
  instance_class    = "db.t3.micro"
  allocated_storage = 20
  storage_type      = "gp2"
  storage_encrypted = true

  db_name                     = var.db_name
  username                    = "appuser"
  manage_master_user_password = true

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.db.id]
  publicly_accessible    = false

  multi_az = false

  backup_retention_period = 1
  skip_final_snapshot     = true
  deletion_protection     = false

  apply_immediately = true

  tags = {
    Name = "${var.project_name}-mysql"
  }
}
