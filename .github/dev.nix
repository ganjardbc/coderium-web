{pkgs}: {
  channel = "stable-24.05";
  packages = [
    pkgs.nodejs_22
    pkgs.docker-compose
  ];
  idx.extensions = [
    "svelte.svelte-vscode",
    "vue.volar"
  ];
  services.docker.enable = true;
  idx.previews = {
    previews = {
      web = {
        command = [
          "npm",
          "run",
          "dev",
          "--",
          "--port",
          "$PORT",
          "--host",
          "0.0.0.0"
        ];
        manager = "web";
      };
    };
  };
}
