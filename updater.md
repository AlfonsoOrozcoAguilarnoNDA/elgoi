# 🚀 Automated Sync Setup Guide (elgoi)

This guide explains how to prepare your server environment to use the **High-Security Synchronizer** PHP script for the `elgoi` repository.

## Important Note

I always recommend using **Debian** because of its simplicity, and occasionally I mention that I use **Rocky Linux** in certain environments.  

If you want to simplify things, the best option is to go with **Debian 13**.  

It is assumed that you already have the **LAMP stack** installed, and that you can copy the `updater.php` file from here into your project.  

I suggest being careful to ensure you are working in the final directory. On Debian, my recommendation would be:  

/var/www/yourdomain.com

This works correctly, but if you try to replicate it identically on **Rocky Linux** or other distributions, you may encounter issues.

## 1. System Requirements

Ensure Git is installed on your server:

* **Debian/Ubuntu:** `sudo apt update && sudo apt install git -y`
* **Rocky Linux/RHEL:** `sudo dnf install git -y`

## 2. Repository Initialization

Navigate to your web directory and initialize the repository. **Note:** You must embed your **Personal Access Token (PAT)** in the remote URL to allow the PHP script to authenticate without manual input.

```bash
# 1. Initialize git
git init

# 2. Add the remote origin with your credentials
# Replace YOUR_TOKEN_HERE with your ghp_... token
git remote add origin [https://user:YOUR_TOKEN_HERE@github.com/alfonsoorozcoaguilarnonda/elgoi.git](https://alfonsoorozcoaguilarnonda:YOUR_TOKEN_HERE@github.com/alfonsoorozcoaguilarnonda/elgoi.git)

# 3. Initial fetch to link branches
git fetch origin

# 4. Force local alignment with main branch
git reset --hard origin/main

# 5. Copy the updater.php to your directory, and run.

