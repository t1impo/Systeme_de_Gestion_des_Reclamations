locals {
  common_tags = {
    Project   = var.project_name
    ManagedBy = "Terraform"
    Owner     = "ecole-cd-pipeline"
  }
}
