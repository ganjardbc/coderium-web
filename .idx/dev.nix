{pkgs}: {
  channel = "unstable";
  packages = [
    pkgs.nodejs_22
    pkgs.docker
    pkgs.docker-compose
    pkgs.php
    pkgs.php84Packages.composer
  ];
  idx.extensions = [
    "svelte.svelte-vscode"
    "vue.volar"
  ];
  services.docker.enable = true;
}
