workflow "New workflow" {
  resolves = ["vale-lint"]
  on = "push"
}

action "vale-lint" {
  uses = "gaurav-nelson/github-action-vale-lint@v0.0.1-alpha"
}
