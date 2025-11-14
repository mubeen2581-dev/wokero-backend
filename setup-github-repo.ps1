# Setup Script for Backend GitHub Repository
# This script initializes a new git repository in the backend folder

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Workero Backend - GitHub Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if we're in the backend directory
$currentDir = Get-Location
if ($currentDir.Name -ne "backend" -and $currentDir.Path -notlike "*\backend") {
    Write-Host "⚠️  Warning: This script should be run from the backend directory" -ForegroundColor Yellow
    Write-Host "Current directory: $currentDir" -ForegroundColor Yellow
    Write-Host ""
    $continue = Read-Host "Continue anyway? (y/n)"
    if ($continue -ne "y") {
        exit
    }
}

# Check if .git already exists
if (Test-Path .git) {
    Write-Host "ℹ️  Git repository already initialized" -ForegroundColor Green
    $existing = Read-Host "Do you want to reinitialize? (y/n)"
    if ($existing -eq "y") {
        Remove-Item -Recurse -Force .git
        Write-Host "✅ Removed existing .git folder" -ForegroundColor Green
    } else {
        Write-Host "Using existing repository..." -ForegroundColor Yellow
    }
}

# Initialize git repository
Write-Host ""
Write-Host "Step 1: Initializing git repository..." -ForegroundColor Cyan
git init
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Failed to initialize git repository" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Git repository initialized" -ForegroundColor Green

# Add all files
Write-Host ""
Write-Host "Step 2: Adding files to repository..." -ForegroundColor Cyan
git add .
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Failed to add files" -ForegroundColor Red
    exit 1
}

# Show what will be committed
Write-Host ""
Write-Host "Files to be committed:" -ForegroundColor Cyan
git status --short | Select-Object -First 20

# Create initial commit
Write-Host ""
Write-Host "Step 3: Creating initial commit..." -ForegroundColor Cyan
git commit -m "Initial commit: Workero Backend API"
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Failed to create commit" -ForegroundColor Red
    Write-Host "Note: If you see 'nothing to commit', files may already be committed or ignored" -ForegroundColor Yellow
    exit 1
}
Write-Host "✅ Initial commit created" -ForegroundColor Green

# Rename branch to main
Write-Host ""
Write-Host "Step 4: Setting default branch to 'main'..." -ForegroundColor Cyan
git branch -M main
Write-Host "✅ Branch renamed to 'main'" -ForegroundColor Green

# Get GitHub repository URL
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Create a new repository on GitHub:" -ForegroundColor Yellow
Write-Host "   - Go to https://github.com/new" -ForegroundColor White
Write-Host "   - Name it (e.g., 'workero-backend')" -ForegroundColor White
Write-Host "   - DO NOT initialize with README, .gitignore, or license" -ForegroundColor White
Write-Host ""
Write-Host "2. Add the remote and push:" -ForegroundColor Yellow
Write-Host "   git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git" -ForegroundColor White
Write-Host "   git push -u origin main" -ForegroundColor White
Write-Host ""
Write-Host "Or run this script with the repository URL:" -ForegroundColor Yellow
Write-Host "   .\setup-github-repo.ps1 -RepoUrl https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git" -ForegroundColor White
Write-Host ""

# Check if RepoUrl parameter was provided
param(
    [string]$RepoUrl
)

if ($RepoUrl) {
    Write-Host "Adding remote repository..." -ForegroundColor Cyan
    git remote add origin $RepoUrl
    if ($LASTEXITCODE -ne 0) {
        Write-Host "⚠️  Failed to add remote (it may already exist)" -ForegroundColor Yellow
        Write-Host "To update remote URL, run: git remote set-url origin $RepoUrl" -ForegroundColor Yellow
    } else {
        Write-Host "✅ Remote added" -ForegroundColor Green
    }
    
    Write-Host ""
    Write-Host "Pushing to GitHub..." -ForegroundColor Cyan
    git push -u origin main
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Successfully pushed to GitHub!" -ForegroundColor Green
    } else {
        Write-Host "❌ Failed to push. Check your credentials and repository URL" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Setup complete! 🎉" -ForegroundColor Green

