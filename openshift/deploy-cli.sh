#!/usr/bin/env bash
# ==============================================================================
# DOCTOR CONNECT - OPENSHIFT CLI DEPLOYMENT SCRIPT
# ==============================================================================
set -e

PROJECT_NAME="doctor-connect"

echo "=== Deploying DoctorConnect to Red Hat OpenShift ==="

# 1. Login verification
if ! oc whoami &>/dev/null; then
    echo "Error: You are not logged into OpenShift. Run 'oc login' first."
    exit 1
fi

# 2. Create or select OpenShift Project
echo "Creating/selecting project: $PROJECT_NAME..."
oc new-project $PROJECT_NAME 2>/dev/null || oc project $PROJECT_NAME

# 3. Deploy MySQL Database & Storage
echo "Deploying MySQL Database..."
oc apply -f openshift/01-mysql.yaml

echo "Waiting for MySQL Pod to become Ready..."
oc rollout status deployment/mysql --timeout=180s

# 4. Build and Deploy Web Application
echo "Creating BuildConfig and starting build from current directory..."
oc new-build --binary --name=doctor-connect-app --strategy=docker 2>/dev/null || true
oc start-build doctor-connect-app --from-dir=. --follow

# 5. Deploy App Deployment, Service, and Route
echo "Deploying Application and Route..."
oc apply -f openshift/02-app.yaml

echo "Waiting for Web Application Pod to roll out..."
oc rollout status deployment/doctor-connect-app --timeout=180s

# 6. Retrieve Public Route URL
ROUTE_HOST=$(oc get route doctor-connect -o jsonpath='{.spec.host}')
echo ""
echo "=================================================================="
echo "🎉 DEPLOYMENT SUCCESSFUL!"
echo "Public URL: https://$ROUTE_HOST"
echo "=================================================================="
