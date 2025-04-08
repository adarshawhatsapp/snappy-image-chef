
module.exports = {
  apps: [{
    name: "image-optimizer",
    script: "./index.js",
    instances: "max",
    exec_mode: "cluster",
    env: {
      NODE_ENV: "production",
      PORT: 3000,
      // This API key is used for authentication in the WordPress plugin
      // You should change this to a secure random string
      // Then use the same API key in the WordPress plugin settings
      API_KEY: "change-me-to-a-secure-random-string"
    }
  }]
}
