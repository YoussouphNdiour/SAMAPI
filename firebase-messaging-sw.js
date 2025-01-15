importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyCii-GMJatZkVscS-pJvUBx28CDjQOLXx8",
    authDomain: "jayma-88682.firebaseapp.com",
    databaseURL: "https://jayma-88682-default-rtdb.europe-west1.firebasedatabase.app",
    projectId: "jayma-88682",
    storageBucket: "jayma-88682.firebasestorage.app",
    messagingSenderId: "484779040551",
    appId: "1:484779040551:web:773b2242f5dfdd38341302",
    measurementId: "G-0ZX2DBWZH7"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});