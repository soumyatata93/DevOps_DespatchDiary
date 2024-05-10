
pipeline {
    agent any
    tools {nodejs "Node JS"}

      environment {
        // Define the path to cmd.exe
        PATH_TO_CMD = 'C:\Windows\System32\cmd.exe'
    }
    stages {
        stage('Example Stage') {
            steps {
                // Use the path to cmd.exe in your commands
                bat "${PATH_TO_CMD} /c echo Hello, world!"
            }
        }
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
