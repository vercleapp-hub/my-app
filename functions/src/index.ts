import * as functions from "firebase-functions";
import * as admin from "firebase-admin";

// Initialize Firebase Admin SDK
admin.initializeApp();

// Callable function to create a user and their Firestore profile
export const createUser = functions.https.onCall(async (data, context) => {
  // 1. Authentication check: Ensure the caller is an admin.
  if (!context.auth || context.auth.token.admin !== true) {
    throw new functions.https.HttpsError(
      "permission-denied",
      "Only administrators can create new users."
    );
  }

  // 2. Input validation
  const { email, password, displayName, role } = data;
  if (!email || !password || !displayName || !role) {
    throw new functions.https.HttpsError(
      "invalid-argument",
      "Missing required fields: email, password, displayName, role."
    );
  }

  try {
    // 3. Create user in Firebase Authentication
    const userRecord = await admin.auth().createUser({
      email,
      password,
      displayName,
    });
    
    // 4. Set custom claims if the role is 'admin'
    if (role === "admin") {
      await admin.auth().setCustomUserClaims(userRecord.uid, { admin: true });
    }

    // 5. Create user profile in Firestore
    const userProfile = {
      uid: userRecord.uid,
      accountNumber: `EL${Date.now()}`,
      displayName: displayName,
      email: email,
      role: role,
      balance: 0,
      totalCommission: 0,
      accountStatus: "active", // Accounts created by admin are active by default
      isActive: true,
      createdAt: admin.firestore.FieldValue.serverTimestamp(),
      // Initialize other fields as empty or default
      fullName: displayName,
      mobile: "",
      nationalId: "",
      address: "",
      businessName: "",
      businessAddress: "",
      idFrontUrl: "",
      idBackUrl: "",
      storePhotoUrl: "",
    };

    await admin.firestore().collection("users").doc(userRecord.uid).set(userProfile);

    functions.logger.log(`Successfully created new user: ${userRecord.uid} with role: ${role}`);
    return { success: true, userId: userRecord.uid, message: "User created successfully." };

  } catch (error: any) {
    functions.logger.error("Error creating new user:", error);
    // Provide a more specific error message if available
    let message = "An internal error occurred while creating the user.";
    if (error.code === 'auth/email-already-exists') {
        message = "This email is already registered.";
    }
    throw new functions.https.HttpsError("internal", message, error);
  }
});


// Cloud Function to set custom user claims (e.g., admin role) when a user's document in Firestore is changed.
export const setUserClaims = functions.firestore
  .document("users/{userId}")
  .onWrite(async (change, context) => {
    const { userId } = context.params;
    const afterData = change.after.data();

    // If the document is deleted, do nothing.
    if (!afterData) {
      functions.logger.log(`User document for ${userId} deleted. No claims to update.`);
      return null;
    }

    const currentRole = afterData.role;
    functions.logger.log(`Processing role change for user ${userId}. New role: ${currentRole}`);

    try {
      // Get the user's current custom claims.
      const user = await admin.auth().getUser(userId);
      const currentClaims = user.customClaims || {};
      
      // If the user's role is 'admin' and they don't have the admin claim yet, set it.
      if (currentRole === "admin" && currentClaims.admin !== true) {
        functions.logger.log(`Setting admin claim for user ${userId}`);
        await admin.auth().setCustomUserClaims(userId, { ...currentClaims, admin: true });
        return { result: `Admin claim set for ${userId}` };
      }
      
      // If the user's role is NOT 'admin' but they currently have the admin claim, remove it.
      if (currentRole !== "admin" && currentClaims.admin === true) {
        functions.logger.log(`Removing admin claim for user ${userId}`);
        // Create a new object without the admin property
        const { admin: _, ...newClaims } = currentClaims;
        await admin.auth().setCustomUserClaims(userId, newClaims);
        return { result: `Admin claim removed for ${userId}` };
      }
      
      functions.logger.log(`No claim change needed for user ${userId}.`);
      return null;

    } catch (error) {
      functions.logger.error(`Error setting custom claims for user ${userId}:`, error);
      return { error: "Failed to set custom claims." };
    }
  });
