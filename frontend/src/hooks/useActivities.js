import { useEffect, useReducer, useCallback } from 'react';
import api from '../services/api';
const initial={items:[],loading:true,error:''};
function reducer(state,action){switch(action.type){case 'loading':return {...state,loading:true,error:''};case 'loaded':return {...state,items:action.items,loading:false};case 'error':return {...state,loading:false,error:action.error};default:return state;}}
export function useActivities(){const [state,dispatch]=useReducer(reducer,initial);const refresh=useCallback(async()=>{dispatch({type:'loading'});try{const {data}=await api.get('/activities');dispatch({type:'loaded',items:data.data});}catch(e){dispatch({type:'error',error:e.message});}},[]);useEffect(()=>{refresh();},[refresh]);return {...state,refresh};}
