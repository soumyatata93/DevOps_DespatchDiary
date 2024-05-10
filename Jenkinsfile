
pipeline {
    agent any
    tools {nodejs "Node JS"}
    stages {
        stage('Build Frontend') {
            steps {
                echo 'Build'
                bat 'npm install'
            }
        }

        stage('Run Tests') {
            steps {
                echo 'Run Test'
            }
        }

        stage('Deploy') {
            steps {
                // Add deployment commands here
                echo 'Deploy'
            }
        }
    }
}
