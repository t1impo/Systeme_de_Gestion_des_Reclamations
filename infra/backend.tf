terraform {
  backend "s3" {
    bucket         = "tf-state-cd-php-app-023036696848"
    key            = "infra/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "terraform-locks-cd-php-app"
    encrypt        = true
  }
}
