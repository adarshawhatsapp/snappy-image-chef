
module.exports = {
  apps: [{
    name: "image-optimizer",
    script: "./index.js",
    instances: "max",
    exec_mode: "cluster",
    env: {
      NODE_ENV: "production",
      PORT: 3000,
      API_KEY: "your-secure-api-key-here"
    }
  }]
}
